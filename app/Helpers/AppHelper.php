<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use App\Models\User;
use App\Models\FrontUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use DateTime;
use DateInterval;
use DatePeriod;
use Mail;
use App;
use Carbon\Carbon;
use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;

class AppHelper
{
    private static $userids=array();

	public static function send_email($from_email, $to_email, $subject, $message){
        if (config('queue.default') !== 'sync') {
            \App\Jobs\SendTransactionalEmailJob::dispatch(
                $from_email,
                $to_email,
                $subject,
                $message
            );

            return [
                'success' => true,
                'mail_dispatched' => true,
                'dispatch_mode' => 'queued',
                'provider_message_id' => null,
                'queue_job_id' => null,
                'error_code' => null,
                'error_message' => null,
            ];
        }

        return self::sendEmailNow($from_email, $to_email, $subject, $message);
	}

    public static function sendEmailNow($from_email, $to_email, $subject, $message){
        $dispatch_mode = 'sync';
        $provider_message_id = null;
        $mailer = (string) config('mail.default');
        $nonDeliverableMailers = ['log', 'array'];

        if (in_array($mailer, $nonDeliverableMailers, true)) {
            $errorMessage = 'Configured mailer does not deliver externally: '.$mailer;
            Log::warning('Email dispatch skipped due to non-deliverable mailer', [
                'mailer' => $mailer,
                'to_email' => $to_email,
                'subject' => $subject,
            ]);

            return [
                'success' => false,
                'mail_dispatched' => false,
                'dispatch_mode' => $dispatch_mode,
                'provider_message_id' => $provider_message_id,
                'queue_job_id' => null,
                'error_code' => 'MAILER_NOT_DELIVERABLE',
                'error_message' => $errorMessage,
            ];
        }

        try {
            $resolved_from_email = $from_email ?: config('mail.from.address');
            $resolved_from_name = config('mail.from.name');

            Mail::send([], [], function ($inner_message) use ($to_email, $subject, $resolved_from_email, $resolved_from_name, $message) {
                $inner_message->to($to_email)
                    ->subject($subject)
                    ->from($resolved_from_email, $resolved_from_name)
                    ->html($message);
            });

            return [
                'success' => true,
                'mail_dispatched' => true,
                'dispatch_mode' => $dispatch_mode,
                'provider_message_id' => $provider_message_id,
                'queue_job_id' => null,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Email dispatch failed', [
                'to_email' => $to_email,
                'subject' => $subject,
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'mail_dispatched' => false,
                'dispatch_mode' => $dispatch_mode,
                'provider_message_id' => $provider_message_id,
                'queue_job_id' => null,
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ];
        }
	}
	public static function get_site_settings(){
		// Settings row changes via admin only. 30 min fresh / 6 h stale absorbs
		// the worst-case "admin edits a flag" delay while removing this query
		// from almost every API request.
		return \App\Support\CookCache::remember('app:site_settings', [1800, 21600], function () {
			return Setting::where('id', 1)->first();
		});
	}
    public static function send_verification_code($medium, $user, $type = 'reset'){
        // Medium 1 for email, 2 for phone
        $verfication_code = self::generateVerificationCode(5);
        $result = [
            'success' => false,
            'mail_dispatched' => false,
            'dispatch_mode' => 'sync',
            'provider_message_id' => null,
            'queue_job_id' => null,
            'error_code' => null,
            'error_message' => null,
            'verification_code' => $verfication_code,
        ];

        if($medium==1){
            $email_to = $user->email;
            $view_name = $type === 'register' ? 'email_templates.front_user.welcome_verification_code' : 'email_templates.front_user.verification_code';
            $html = view($view_name, compact('user', 'verfication_code'))->render();
            $subject = $type === 'register' ? 'Welcome Verification' : 'Email Verification';
            $result = array_merge($result, self::send_email(env('MAIL_FROM_ADDRESS'), $email_to, $subject, $html));

            if (!$result['success']) {
                return $result;
            }
        }

        DB::table('verification_codes')->where('medium', $medium)->where('front_user_id', $user->id)->delete();
        DB::table('verification_codes')->insert([
            'medium' => $medium,
            'front_user_id' => $user->id,
            'code' => $verfication_code,
        ]);

        $result['success'] = true;
        return $result;
    }
    public static function generateVerificationCode($length = 5) {
        $characters = '0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
    public static function get_works(){
        $language = App::getLocale();
        $query = DB::table('works');
        $query->join('works_description', 'works_description.work_id', '=', 'works.id');
        $query->join('site_languages', 'works_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->orderBy('works.sort_order', 'ASC');
        $works = $query->select(['works.*', 'works_description.title', 'works_description.number', 'works_description.description'])->get();
        return $works;
    }

    /**
     * Per-request memoization of the media base URL. decorateVideoRow() is
     * called for every video in a feed page (up to 30+ per request), and the
     * underlying config never changes within a request, so we resolve once.
     */
    private static ?string $cachedMediaBaseUrl = null;

    /**
     * Public base URL for objects on the S3/GCS disk (trailing slash).
     * Prefer AWS_CLOUD_FRONT_PATH; fall back to AWS_URL (matches admin + API).
     */
    public static function mediaPublicBaseUrl(): string
    {
        if (self::$cachedMediaBaseUrl !== null) {
            return self::$cachedMediaBaseUrl;
        }

        // Must use config(): env() is empty at runtime when config is cached (breaks mobile video URLs).
        $cdn = app(\App\Services\CdnService::class);
        $base = $cdn->shouldUseCdn()
            ? rtrim((string) (config('cdn.base_url') ?? ''), '/')
            : $cdn->directPublicBaseUrl();
        if ($base === '') {
            $base = rtrim((string) (config('filesystems.disks.s3.cloudfront_path') ?? ''), '/');
        }
        if ($base === '') {
            $base = rtrim((string) (config('filesystems.disks.s3.url') ?? ''), '/');
        }

        $bucket = (string) (config('filesystems.disks.s3.bucket') ?? '');
        // Common misconfig: AWS_URL copied from AWS_ENDPOINT → https://storage.googleapis.com with no bucket segment.
        // Public object URLs must be …/BUCKET/videos/… (path-style) or https://BUCKET.storage.googleapis.com/…
        if ($bucket !== '' && preg_match('#^https?://storage\.googleapis\.com/?$#i', $base)) {
            $base = rtrim($base, '/').'/'.$bucket;
        }

        return self::$cachedMediaBaseUrl = ($base === '' ? '' : $base.'/');
    }

    /**
     * @param  non-empty-string  $baseWithSlash  From mediaPublicBaseUrl()
     */
    public static function absoluteUrlForStoredObject(string $baseWithSlash, string $stored, string $defaultPrefix): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        $stored = ltrim(str_replace('\\', '/', $stored), '/');
        if (str_starts_with($stored, 'videos/')) {
            return rtrim($baseWithSlash, '/').'/'.$stored;
        }

        return rtrim($baseWithSlash, '/').'/'.ltrim($defaultPrefix, '/').$stored;
    }

    /**
     * Object storage key for a profile/cover image filename.
     */
    public static function userImageStorageKey(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }

        $image = trim($image);

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            $path = parse_url($image, PHP_URL_PATH);

            return $path !== null && $path !== '' ? ltrim($path, '/') : null;
        }

        if (str_starts_with($image, 'storage/front_users/')) {
            return ltrim(str_replace('\\', '/', $image), '/');
        }

        if (str_starts_with($image, 'front_users/')) {
            return 'storage/'.$image;
        }

        return 'storage/front_users/'.basename(str_replace('\\', '/', $image));
    }

    /**
     * Resolve a profile/cover image to a single absolute URL (no double-prefix).
     */
    public static function userImageUrl(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }

        $image = trim($image);

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        $key = self::userImageStorageKey($image);
        if ($key === null) {
            return null;
        }

        $cdn = app(\App\Services\CdnService::class);
        if ($cdn->shouldUseCdn()) {
            return $cdn->urlForPath($key);
        }

        return url('storage/front_users/'.basename(str_replace('\\', '/', $image)));
    }

    /** @param \Illuminate\Support\Collection|array<int, object> $users */
    public static function decorateUserIterable($users): void
    {
        if ($users instanceof \Illuminate\Support\Collection) {
            foreach ($users as $user) {
                self::decorateUserRow($user);
            }

            return;
        }

        if (is_array($users)) {
            foreach ($users as $user) {
                if (is_object($user)) {
                    self::decorateUserRow($user);
                }
            }
        }
    }

    public static function decorateUserRow(object $user): void
    {
        if (isset($user->image)) {
            $user->image_url = self::userImageUrl((string) $user->image);
        }
    }

    /**
     * Mobile contract: is_image is 1 for photo posts, 0 for video.
     */
    public static function normalizeIsImage(object $v): int
    {
        if (isset($v->is_image) && ($v->is_image === true || $v->is_image === 1 || $v->is_image === '1')) {
            return 1;
        }

        $image = isset($v->image) ? trim((string) $v->image) : '';
        $video = isset($v->video) ? trim((string) $v->video) : '';

        if ($image !== '' && \App\Services\VideoMediaService::isStaticImageFilename($image)) {
            if ($video === '' || \App\Services\VideoMediaService::isStaticImageFilename($video)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Add video_url / image_url / thumbnail_url and optionally rewrite video & image to absolute URLs
     * so mobile players that pass the field straight to VideoPlayer/Image.network work with GCS.
     *
     * Set API_MEDIA_USE_ABSOLUTE_VIDEO_URL=false to only add * _url fields and keep legacy filenames.
     */
    public static function decorateVideoRow(object $v): void
    {
        $cdn = app(\App\Services\CdnService::class);
        $pathVideo = isset($v->video) ? (string) $v->video : '';
        $pathImage = isset($v->image) ? (string) $v->image : '';
        $videoId = isset($v->id) ? (string) $v->id : '';
        $transcodeStatus = isset($v->transcode_status) ? (string) $v->transcode_status : 'pending';
        $processingStatus = isset($v->processing_status) ? (string) $v->processing_status : null;
        $isImage = self::normalizeIsImage($v);
        $v->is_image = $isImage;
        $isPhotoPost = $isImage === 1;
        $isHlsReady = $transcodeStatus === 'ready';

        $videoKey = $pathVideo !== ''
            ? (str_starts_with($pathVideo, 'videos/') ? $pathVideo : 'videos/'.$pathVideo)
            : null;
        $imageKey = $pathImage !== ''
            ? (str_starts_with($pathImage, 'videos/') ? $pathImage : 'videos/'.$pathImage)
            : null;

        $v->video_url = $videoKey ? $cdn->urlForPath($videoKey) : null;
        $v->image_url = \App\Services\VideoMediaService::resolveCoverImageUrl($pathImage !== '' ? $pathImage : null);
        $v->thumbnail_url = $videoId !== ''
            ? \App\Services\VideoMediaService::resolvePosterUrl($videoId, $pathImage !== '' ? $pathImage : null, $processingStatus)
            : null;
        $v->thumbnail = $v->thumbnail_url;
        $v->thumbnail_blur = $videoId !== ''
            ? \App\Services\VideoMediaService::resolvePosterBlurUrl($videoId, $processingStatus)
            : null;

        if ($isPhotoPost) {
            $fullImageUrl = \App\Services\VideoMediaService::resolvePhotoFullImageUrl(
                $pathImage !== '' ? $pathImage : null,
                $pathVideo !== '' ? $pathVideo : null,
            );
            $photoThumbUrl = \App\Services\VideoMediaService::resolvePhotoThumbnailUrl(
                $pathImage !== '' ? $pathImage : null,
            );

            $v->video_url = $fullImageUrl;
            $v->image_url = $fullImageUrl;
            $v->thumbnail_url = $photoThumbUrl ?? $fullImageUrl;
            $v->thumbnail = $v->thumbnail_url;
            $v->video_sources = ['url_360' => null, 'url_720' => null, 'url_1080' => null];
            $v->hls_playlist_url = null;
            $v->hls_url = null;
            if (config('cdn.expose_direct_urls')) {
                $photoKey = $pathImage !== ''
                    ? (str_starts_with($pathImage, 'videos/') ? $pathImage : 'videos/'.$pathImage)
                    : null;
                $v->video_url_direct = $photoKey ? $cdn->directUrlForPath($photoKey) : null;
                $v->image_url_direct = $v->video_url_direct;
                $v->hls_playlist_url_direct = null;
                if ($photoThumbUrl !== null && $pathImage !== '') {
                    $thumbBasename = basename(str_replace('\\', '/', $pathImage));
                    $v->thumbnail_url_direct = $cdn->directUrlForPath('videos/thumbnail/'.$thumbBasename);
                }
            }
        }

        $v->transcode_status = $transcodeStatus;
        $v->processing_status = $processingStatus ?? ($isHlsReady ? 'ready' : 'processing');

        if (! $isPhotoPost) {
            $v->video_sources = $videoId !== ''
                ? \App\Services\VideoMediaService::videoSources($videoId, $isHlsReady)
                : ['url_360' => null, 'url_720' => null, 'url_1080' => null];

            $hlsKey = $videoId !== ''
                ? \App\Services\VideoMediaService::resolveHlsKey(isset($v->hls_url) ? (string) $v->hls_url : null, $videoId, $isHlsReady)
                : null;

            if (config('cdn.expose_direct_urls')) {
                $v->video_url_direct = $videoKey ? $cdn->directUrlForPath($videoKey) : null;
                $v->image_url_direct = $imageKey ? $cdn->directUrlForPath($imageKey) : null;
                $posterKey = ($processingStatus ?? '') === 'ready' && $videoId !== ''
                    ? \App\Services\VideoMediaService::posterKey($videoId)
                    : null;
                $v->thumbnail_url_direct = $posterKey ? $cdn->directUrlForPath($posterKey) : null;
                $v->hls_playlist_url = $hlsKey ? $cdn->urlForPath($hlsKey) : null;
                $v->hls_playlist_url_direct = $hlsKey ? $cdn->directUrlForPath($hlsKey) : null;
            } else {
                $v->hls_playlist_url = $hlsKey ? $cdn->urlForPath($hlsKey) : null;
            }

            $v->hls_url = $v->hls_playlist_url ?? null;
        }

        $v->playback_ready = $isPhotoPost || $isHlsReady;
        if (! $isHlsReady && ! $isPhotoPost) {
            // Do not expose the raw upload MP4 while transcoding — MTK/MediaKit often
            // decodes audio but fails to render (renderFps=0). Posters + status fields only.
            $v->video_url = null;
            $v->video_url_direct = null;
            $v->hls_playlist_url = null;
            $v->hls_playlist_url_direct = null;
            $v->hls_url = null;
            $v->video_sources = ['url_360' => null, 'url_720' => null, 'url_1080' => null];
        }

        if (isset($v->user_image)) {
            $v->user_image_url = self::userImageUrl((string) $v->user_image);
        }

        $useAbsolute = (bool) config('filesystems.disks.s3.api_media_absolute_urls', true);
        if (! $useAbsolute) {
            if (! $isHlsReady && ! $isPhotoPost) {
                $v->video = null;
            }

            return;
        }

        if ($pathVideo !== '' && ! str_starts_with($pathVideo, 'http://') && ! str_starts_with($pathVideo, 'https://')) {
            if ($isPhotoPost) {
                $v->video = $v->video_url;
            } else {
                $v->video = ($isHlsReady || $isPhotoPost) ? $v->video_url : null;
            }
        }
        if ($pathImage !== '' && ! str_starts_with($pathImage, 'http://') && ! str_starts_with($pathImage, 'https://')) {
            $v->image = $v->image_url;
        }
    }

    /** @param \Illuminate\Support\Collection|array<int, object> $videos */
    public static function decorateVideoIterable($videos): void
    {
        if ($videos instanceof \Illuminate\Support\Collection) {
            foreach ($videos as $v) {
                self::decorateVideoRow($v);
            }

            return;
        }
        if (is_array($videos)) {
            foreach ($videos as $v) {
                if (is_object($v)) {
                    self::decorateVideoRow($v);
                }
            }
        }
    }

    public static function custom_number_format($number, $decimals){
        return number_format($number, $decimals, '.', ',');
    }
    public static function currency_formatter($number){
        $site_settings = self::get_site_settings();
        if($site_settings){
            return $site_settings->currency_symbol . number_format($number, 2, '.', ',');
        }
        else{
            return number_format($number, 2, '.', ',');
        }
    }
    public static function get_key_values($key_id, $language = ""){
        $language = App::getLocale();

        // Reference data — changes via admin only. Cache aggressively (1 day fresh,
        // 7 day stale via SWR). This function is called by `video_settings`, the
        // profile endpoint's video-type loop, sponsor type lookups, etc., so the
        // savings compound across all hot endpoints.
        return \App\Support\CookCache::remember(
            'app:key_values:'.$key_id.':'.$language,
            [86400, 604800],
            function () use ($key_id, $language) {
                $query = DB::table('generic_keys')
                    ->join('generic_keys_description', 'generic_keys.id', '=', 'generic_keys_description.key_id')
                    ->join('site_languages as key_language', 'generic_keys_description.language_id', '=', 'key_language.id');
                if ($language) {
                    $query->where('key_language.code', $language);
                } else {
                    $query->where('key_language.is_default', 1);
                }
                $query->where('generic_keys.id', $key_id);
                $key = $query->select(['generic_keys.*', 'generic_keys_description.name as key_name'])->first();

                $query = DB::table('generic_key_values')
                    ->join('generic_key_values_description', 'generic_key_values_description.value_id', '=', 'generic_key_values.id')
                    ->join('site_languages', 'generic_key_values_description.language_id', '=', 'site_languages.id');
                if ($language) {
                    $query->where('site_languages.code', $language);
                } else {
                    $query->where('site_languages.is_default', 1);
                }
                $query->where('generic_key_values.status', 1)->where('generic_key_values.key_id', $key_id);
                $values = $query->select(['generic_key_values.*', 'generic_key_values_description.name'])->get();

                return [
                    'key' => $key,
                    'values' => $values,
                ];
            }
        );
    }
    public static function get_key_values_by_value_ids($value_ids, $language = ""){
        $query=DB::table('generic_key_values');
        $query->join('generic_key_values_description', 'generic_key_values_description.value_id', '=', 'generic_key_values.id');
        $query->join('site_languages', 'generic_key_values_description.language_id', '=', 'site_languages.id');
        if($language){
            $query->where('site_languages.code', $language);
        }
        else{
            $query->where('site_languages.is_default', 1);
        }
        $query->where('generic_key_values.status', 1);
        $query->whereIn('generic_key_values.id', $value_ids);
        $values = $query->select(['generic_key_values.*', 'generic_key_values_description.name'])->get();
        return $values;
    }
    public static function id_formatter($type, $id){
        $return_data = '';
        if($type==1){
            $return_data = env('PERSONAL_ACCOUNTS_PREFIX').$id;
        }
        else if($type==2){
            $return_data = env('BUSINESS_ACCOUNTS_PREFIX').$id;
        }
        else if($type==3){
            $return_data = env('CHEF_ACCOUNTS_PREFIX').$id;
        }
        else if($type==4){
            $return_data = env('VIDEO_PREFIX').$id;
        }
        else if($type==5){
            $return_data = env('SPONSORED_ACCOUNTS_PREFIX').$id;
        }
        else if($type==6){
            $return_data = env('USER_PAYMENT_PREFIX').$id;
        }
        else if($type==7){
            $return_data = env('USER_REVIEW_PREFIX').$id;
        }
        return $return_data;
    }
    public static function run_add_tag_script($tags){
        if (! $tags) {
            return true;
        }

        // Was N+1 (one SELECT per tag, plus one INSERT each). Now: one SELECT
        // for all existing tags, then a single bulk INSERT for the new ones.
        $tagList = array_values(array_filter(array_map('trim', explode(',', $tags)), fn($t) => $t !== ''));
        if (empty($tagList)) {
            return true;
        }

        $existing = DB::table('tags')
            ->whereIn('name', $tagList)
            ->pluck('name')
            ->all();
        $existingSet = array_flip($existing);

        $now = now();
        $toInsert = [];
        foreach (array_unique($tagList) as $tag) {
            if (! isset($existingSet[$tag])) {
                $toInsert[] = ['name' => $tag, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if (! empty($toInsert)) {
            DB::table('tags')->insert($toInsert);
        }

        return true;
    }
    public static function get_user_details($user_id){
        return DB::table('front_users')->where('id', $user_id)->first();
    }
    public static function get_sponsor_type_label($type){
        $label = "";
        if($type==1){
            $label = '<label class="badge bg-primary">Basic</label>';
        }
        else if($type==2){
            $label = '<label class="badge bg-info">Premium</label>';
        }
        return $label;
    }
    public static function get_cities_names($ids){
        $city_names = '';
        if($ids){
            $ids = explode(',',$ids);
            $city_names_array = DB::table('cities')->whereIn('id', $ids)->pluck('name')->toArray();
            if($city_names_array){
                $city_names = implode(', ', $city_names_array);
            }
        }
        return $city_names;
    }
    public static function subscribe_user_to_package($user_id, $package_id, $payment_data = NULL){
        $query=DB::table('packages');
        $query->join('packages_description', 'packages_description.package_id', '=', 'packages.id');
        $query->join('site_languages', 'packages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.is_default', 1);
        $query->where('packages.id', $package_id);
        $query->orderBy('packages.system_id', 'ASC');
        $package_details = $query->select(['packages.*', 'packages_description.title', 'packages_description.description'])->first();

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['front_user_id'] = $user_id;
        $ins_data['package_id'] = $package_details->id;
        $ins_data['start_date'] = date('Y-m-d');
        $ins_data['end_date'] = date('Y-m-d', strtotime('+'.$package_details->duration.' months'));
        $ins_data['duration'] = $package_details->duration;
        $ins_data['amount'] = $package_details->amount;
        DB::table('subscription_history')->insert($ins_data);

        $up_data=array();
        $up_data['current_subscription_id'] = $ins_data['id'];
        DB::table('front_users')->where('id',$user_id)->update($up_data);

        if($payment_data){
            self::add_user_payment(1, $ins_data['id'], $user_id, $package_details->amount, $payment_data);
        }

        return true;
    }
    public static function get_unread_notifications(){
        return DB::table('notifications')->where('to_type', 1)->where('read_status', 0)->orderBy('id', 'DESC')->get();
    }
    /**
     * Build the human-readable details for a notification row.
     *
     * @param  object  $notification     Notifications table row.
     * @param  array|null  $preloaded   Optional map of pre-fetched related rows
     *                                  returned by ApiController::preloadNotificationRelations()
     *                                  with keys: reports, pushes, videos, reviews, users.
     *                                  Pass it for lists to avoid N+1; single-notification
     *                                  callers can omit and we fall back to DB lookups.
     */
    public static function get_notification_subject_text($notification, ?array $preloaded = null){
        $details = [];
        $details['href'] = '';
        $type = (int) $notification->type;

        // Use cached config so the format survives `config:cache` (env() returns
        // null inside cached-config processes which would crash strtotime/date).
        $dateFormat = (string) (config('cookster.formats.date_time') ?: 'd-M-Y h:i A');
        $formattedDate = date($dateFormat, strtotime($notification->created_at));

        // Helper closures resolve from preloaded map, or fall back to a single DB
        // query when callers haven't passed one (keeps legacy callers working).
        $report = fn($id) => $preloaded['reports'][$id]
            ?? DB::table('video_reports')->where('id', $id)->first();
        $push = fn($id) => $preloaded['pushes'][$id]
            ?? DB::table('push_notifications')->where('id', $id)->first();
        $video = fn($id) => $preloaded['videos'][$id]
            ?? DB::table('videos')->where('id', $id)->select(['id','title'])->first();
        $review = fn($id) => $preloaded['reviews'][$id]
            ?? DB::table('user_reviews')->join('front_users', 'front_users.id', '=', 'user_reviews.reviewer_id')
                ->where('user_reviews.id', $id)
                ->select(['user_reviews.id','user_reviews.reviewer_id','front_users.name as reviewer_name'])
                ->first();
        $user = fn($id) => $preloaded['users'][$id]
            ?? DB::table('front_users')->where('id', $id)->select(['id','name'])->first();

        if ($type === 1) {
            $video_details = $report($notification->video_report_id);
            $details['subject'] = __('messages.video_reported');
            $details['text'] = __('messages.video_reported_msg');
            if ($video_details) {
                $details['href'] = url('admin/videos/'.$video_details->video_id.'?notification_id='.$notification->id);
            } else {
                $details['text'] = __('messages.video_not_found_msg');
            }
        } elseif ($type === 2) {
            $push_notifications_details = $push($notification->push_notification_id);
            $details['title'] = $push_notifications_details->title ?? '';
            $details['text'] = $push_notifications_details->text ?? '';
        } elseif ($type === 3) {
            $video_details = $video($notification->video_id);
            $details['title'] = __('messages.deactivated_video');
            if ($video_details) {
                $details['text'] = __('messages.your_video') . ' ('.$video_details->title.') ' . __('messages.deactivated_video_msg');
            } else {
                $details['text'] = __('messages.video_not_found_msg');
            }
        } elseif ($type === 4) {
            $user_reviews_details = $review($notification->user_review_id);
            $details['title'] = __('messages.new_user_review');
            $reviewerName = $user_reviews_details->reviewer_name ?? '';
            $details['text'] = __('messages.user') . ' (' . $reviewerName . ') ' . __('messages.new_user_review_msg');
        } elseif ($type === 5) {
            $user_details = $user($notification->front_user_id);
            $video_details = $video($notification->video_id);
            $details['title'] = __('messages.user_liked_a_video');
            if ($video_details) {
                $userName = $user_details->name ?? '';
                $details['text'] = __('messages.user') . ' ('.$userName.') ' . __('messages.has_liked_your_video') . ' ('.$video_details->title.').';
            } else {
                $details['text'] = __('messages.video_not_found_msg');
            }
        }

        $details['date_time'] = $formattedDate;

        return $details;
    }
    public static function send_push_notification($push_notification_text, $deviceTokens){
        if ($deviceTokens === [] || $deviceTokens === null) {
            return;
        }

        if (config('queue.default') !== 'sync') {
            \App\Jobs\SendPushNotificationJob::dispatch($push_notification_text, $deviceTokens);

            return;
        }

        self::sendPushNotificationNow($push_notification_text, $deviceTokens);
    }

    public static function sendPushNotificationNow($push_notification_text, $deviceTokens){
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        // create middleware
        $middleware = ApplicationDefaultCredentials::getMiddleware($scopes);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        $client = new Client([
            'handler' => $stack,
            'auth' => 'google_auth'
        ]);
      
        $messages = [];

        foreach ($deviceTokens as $token) {
            $notification_data = array_map('strval', $push_notification_text['notification_data']);
            $single_message_data = [
                'token' => $token,
                'notification' => [
                    'title' => $push_notification_text['title'],
                    'body' => $push_notification_text['text'],
                ],
                'data' => $notification_data,
            ];
            $messages[] = $single_message_data;
        }

        ### Create message request promises
        $promises = function() use ($client, $messages) {
            foreach ($messages as $message) {
                yield $client->requestAsync('POST', 'https://fcm.googleapis.com/v1/projects/cockster-e477a/messages:send', [
                    'json' => ['message' => $message],
                ]);
            }
        };
        ### Create response handler
        $handleResponses = function (array $responses) {
            foreach ($responses as $response) {
                if ($response['state'] === Promise\PromiseInterface::FULFILLED) {
                    // $response['value'] is an instance of \Psr\Http\Message\RequestInterface
                    // echo $response['value']->getBody();
                } elseif ($response['state'] === Promise\PromiseInterface::REJECTED) {
                    // $response['reason'] is an exception
                    // echo $response['reason']->getMessage();
                }
            }
        };
        Promise\Utils::settle($promises())
            ->then($handleResponses)
            ->wait();
    }
    public static function send_push_notification_topic($push_notification_text, $topic){
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        // create middleware
        $middleware = ApplicationDefaultCredentials::getMiddleware($scopes);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        $client = new Client([
            'handler' => $stack,
            'auth' => 'google_auth'
        ]);
      
        $messages = [];
        $notification_data = array_map('strval', $push_notification_text['notification_data']);
        $single_message_data = [
            'topic' => $topic,
            'notification' => [
                'title' => $push_notification_text['title'],
                'body' => $push_notification_text['text'],
            ],
            'data' => $notification_data,
        ];
        $messages[] = $single_message_data;

        ### Create message request promises
        $promises = function() use ($client, $messages) {
            foreach ($messages as $message) {
                yield $client->requestAsync('POST', 'https://fcm.googleapis.com/v1/projects/cockster-e477a/messages:send', [
                    'json' => ['message' => $message],
                ]);
            }
        };
        ### Create response handler
        $handleResponses = function (array $responses) {
            foreach ($responses as $response) {
                if ($response['state'] === Promise\PromiseInterface::FULFILLED) {
                    // $response['value'] is an instance of \Psr\Http\Message\RequestInterface
                    // echo $response['value']->getBody();
                } elseif ($response['state'] === Promise\PromiseInterface::REJECTED) {
                    // $response['reason'] is an exception
                    // echo $response['reason']->getMessage();
                }
            }
        };
        Promise\Utils::settle($promises())
            ->then($handleResponses)
            ->wait();
    }
    public static function call_curl_request($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL issues (not recommended for production)

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            // throw new Exception('cURL Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($response, true);
    }
    public static function add_user_sponsor_video($video_id, $cities, $sponsor_type, $days, $payment_data = NULL){
        $user = Auth::user();
        $user_entity_details = DB::table('entities')->where('id', $user->entity)->first();
        $settings = DB::table('settings')->where('id', 1)->select(['basic_sponsored_video_price', 'premium_sponsored_video_price', 'sponsor_video_discount'])->first();
        $no_of_cities = count(explode(',', $cities));
        $per_day_price = 0;
        $discount_percentage = 0;
        $discount_amount = 0;
        $total_amount = 0;

        if($sponsor_type==1){
            $per_day_price = $settings->basic_sponsored_video_price;
        }
        else if($sponsor_type==2){
            $per_day_price = $settings->premium_sponsored_video_price;
        }
        $total_amount = $per_day_price*$days*$no_of_cities;
        if($user_entity_details->subscription_required == 1){
            $isExpired = self::check_subscription_expired($user->id);
            if($isExpired){
                return false;
            }

            $discount_percentage = $settings->sponsor_video_discount;
            if($discount_percentage>0){
                $discount_amount = ($total_amount*$discount_percentage)/100;
            }
        }

        $ins_data=array();
        $ins_data['video_id'] = $video_id;
        $ins_data['cities'] = $cities;
        $ins_data['sponsor_type'] = $sponsor_type;
        $ins_data['days'] = $days;
        $ins_data['start_date'] = date('Y-m-d H:i:s');
        $ins_data['end_date'] = date('Y-m-d H:i:s', strtotime('+'.$days.' days'));
        $ins_data['per_day_price'] = $per_day_price;
        $ins_data['discount_percentage'] = $discount_percentage;
        $ins_data['discount_amount'] = $discount_amount;
        $ins_data['total_amount'] = $total_amount-$discount_amount;

        $sponsored_videos_data = $ins_data;
        $sponsored_videos_data['id'] = (string) \Str::uuid();
        $sponsored_videos_history_data = $ins_data;
        $sponsored_videos_history_data['id'] = (string) \Str::uuid();

        DB::table('sponsored_videos')->where('video_id', $video_id)->delete();

        DB::table('sponsored_videos')->insert($sponsored_videos_data);
        DB::table('sponsored_videos_history')->insert($sponsored_videos_history_data);

        if($payment_data){
            self::add_user_payment(2, $sponsored_videos_history_data['id'], $user->id, $total_amount-$discount_amount, $payment_data);
        }

        return true;
    }
    public static function check_subscription_expired($front_user_id){
        $isExpired = DB::table('subscription_history')->where('front_user_id', $front_user_id)
            ->orderByDesc('end_date')
            ->first()?->end_date < Carbon::now();
            
        return $isExpired;
    }
    public static function add_user_payment($payment_for, $external_id, $user_id, $amount, $payment_data){
        // $payment_for = 1:Subscription, 2:Sponsor

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['payment_for'] = $payment_for;
        $ins_data['external_id'] = $external_id;
        $ins_data['user_id'] = $user_id;
        $ins_data['amount'] = $amount;
        $ins_data['PaymentId'] = $payment_data['PaymentId'];
        $ins_data['TranId'] = $payment_data['TranId'];
        $ins_data['ECI'] = $payment_data['ECI'];
        $ins_data['TrackId'] = $payment_data['TrackId'];
        $ins_data['RRN'] = $payment_data['RRN'];
        $ins_data['cardBrand'] = $payment_data['cardBrand'];
        $ins_data['maskedPAN'] = $payment_data['maskedPAN'];
        $ins_data['PaymentType'] = $payment_data['PaymentType'];

        DB::table('user_payments')->insert($ins_data);

        return true;
    }
    public static function add_user_loyalty_points($customer_id, $business_id, $type, $points){
        // $type = 1:Points Earned, 2:Points Used

        $customer_user = FrontUser::where('id', $customer_id)->first();
        $credit = 0;
        $debit = 0;

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['customer_id'] = $customer_id;
        $ins_data['business_id'] = $business_id;
        $ins_data['type'] = $type;

        if($type == 1){
            $credit = $points;
        }
        else if($type == 2){
            $debit = $points;
        }

        $current_balance = $customer_user->total_loyalty_points + $credit - $debit;

        $ins_data['credit'] = $credit;
        $ins_data['debit'] = $debit;
        $ins_data['balance'] = $current_balance;

        DB::table('user_loyalty_points_history')->insert($ins_data);

        $customer_user->total_loyalty_points = $current_balance;
        $customer_user->save();

        return $customer_user->total_loyalty_points;
    }
    public static function add_user_qrcode_scan_history($business_id, $customer_id, $points, $amount){
        $business_user = FrontUser::where('id', $business_id)->first();

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['business_id'] = $business_id;
        $ins_data['customer_id'] = $customer_id;
        $ins_data['points'] = $points;
        $ins_data['amount'] = $amount;

        $current_balance = $business_user->total_outstanding_balance + $amount;

        $ins_data['balance'] = $current_balance;

        DB::table('user_qrcode_scan_history')->insert($ins_data);

        $business_user->total_outstanding_balance = $current_balance;
        $business_user->save();

        return $business_user->total_outstanding_balance;
    }
    public static function add_one_time_discount_history($business_id, $customer_id, $amount){
        $business_account_additional_data = DB::table('business_account_additional_data')->where('front_user_id', $business_id)->first();

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['business_id'] = $business_id;
        $ins_data['customer_id'] = $customer_id;
        $ins_data['amount'] = $amount;

        $current_balance = $business_account_additional_data->one_time_discount_outstanding_balance + $amount;

        $ins_data['balance'] = $current_balance;

        DB::table('one_time_discount_history')->insert($ins_data);

        DB::table('business_account_additional_data')->where('id', $business_account_additional_data->id)->update([
            'one_time_discount_outstanding_balance' => $current_balance
        ]);

        return $current_balance;
    }
}