<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Post-disaster hydration: admin, permissions, settings, languages, CMS pages, categories, entities,
 * generic keys/values (registration + forms), packages, banners, onboarding screens, works, blog categories,
 * minimal countries/states/cities (Saudi + UAE; city IDs aligned with cookster.sql for stable admin/API use).
 *
 * Admin password: set env RECOVERY_ADMIN_PASSWORD (otherwise a one-time default is used — change immediately).
 * This app has no `users.role` column; admin access uses Spatie role "Admin".
 */
class RecoveryHydrationSeeder extends Seeder
{
    /** Stable UUID so re-running the seeder does not duplicate video/report categories. */
    private const CATEGORY_TYPE1_ID = 'c00c0001-0000-4000-8000-000000000001';

    private const CATEGORY_TYPE2_ID = 'c00c0002-0000-4000-8000-000000000002';

    private function rowForTable(string $table, array $desired): array
    {
        $cols = array_flip(Schema::getColumnListing($table));

        return array_intersect_key($desired, $cols);
    }

    public function run(): void
    {
        $now = now();

        // --- 1) Super admin (Spatie; not a `role` column on users) ---
        $plain = env('RECOVERY_ADMIN_PASSWORD', 'CooksterRecovery!ChangeMe');
        $user = User::updateOrCreate(
            ['email' => 'admin@cookster.org'],
            ['name' => 'Super Admin', 'password' => $plain]
        );

        $guard = config('auth.defaults.guard', 'web');
        $this->ensureAllAdminPermissionsExist($guard);
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guard]);
        // Grant every permission on this guard (full site admin, including any pre-existing rows).
        $role->syncPermissions(Permission::where('guard_name', $guard)->get());
        $user->syncRoles([$role]);

        // --- 2) Settings id = 1 (only columns that exist on this database) ---
        $settingsDesired = [
            'id' => 1,
            'email' => 'info@cookster.org',
            'phone' => '',
            'address' => '',
            'facebook' => null,
            'twitter' => null,
            'instagram' => null,
            'linkedin' => null,
            'tiktok' => null,
            'snapchat' => null,
            'basic_sponsored_video_price' => 0,
            'premium_sponsored_video_price' => 0,
            'sponsor_video_discount' => 0,
            'allow_general_videos' => 1,
            'allow_following_videos' => 1,
            'currency_symbol' => '$',
            'play_store_link' => 'https://play.google.com/store/apps/details?id=com.cookster',
            'app_store_link' => 'https://apps.apple.com/app/cookster',
            'first_loyalty_points' => 0,
            'loyalty_points' => 0,
            'loyalty_points_exchange_rate' => 1,
            'loyalty_points_status' => 1,
            'allow_one_time_qr_reward' => 0,
            'status' => 1,
            'created_date' => $now,
        ];
        $settingsRow = $this->rowForTable('settings', $settingsDesired);
        unset($settingsRow['id']);
        DB::table('settings')->updateOrInsert(['id' => 1], $settingsRow);

        // --- 3) Site languages (required for page/category joins) ---
        if (Schema::hasTable('site_languages')) {
            DB::table('site_languages')->updateOrInsert(
                ['id' => 1],
                $this->rowForTable('site_languages', [
                    'id' => 1,
                    'name' => 'English',
                    'code' => 'en',
                    'image' => 'en.png',
                    'direction' => 'ltr',
                    'is_default' => 1,
                    'sort_order' => 1,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
            DB::table('site_languages')->updateOrInsert(
                ['id' => 2],
                $this->rowForTable('site_languages', [
                    'id' => 2,
                    'name' => 'Arabic',
                    'code' => 'ar',
                    'image' => 'ar.png',
                    'direction' => 'rtl',
                    'is_default' => 0,
                    'sort_order' => 2,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        // --- 4) Pages 1–6 + descriptions (home uses page id 5; blog uses 6) ---
        $pageDefs = [
            1 => ['image' => '', 'status' => 1],
            2 => ['image' => '', 'status' => 1],
            3 => ['image' => '', 'status' => 1],
            4 => ['image' => '', 'status' => 1],
            5 => ['image' => '', 'status' => 1],
            6 => ['image' => '', 'status' => 1],
        ];
        foreach ($pageDefs as $pid => $p) {
            DB::table('pages')->updateOrInsert(
                ['id' => $pid],
                $this->rowForTable('pages', array_merge(['id' => $pid, 'created_at' => $now, 'updated_at' => $now], $p))
            );
        }

        $titles = [
            1 => ['en' => 'About Us', 'ar' => 'من نحن'],
            2 => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
            3 => ['en' => 'Terms of Use', 'ar' => 'شروط الاستخدام'],
            4 => ['en' => 'Contact Us', 'ar' => 'اتصل بنا'],
            5 => ['en' => 'Home', 'ar' => 'الرئيسية'],
            6 => ['en' => 'Blog', 'ar' => 'مدونة'],
        ];

        foreach ($titles as $pageId => $langTitles) {
            foreach ([1 => 'en', 2 => 'ar'] as $languageId => $code) {
                $title = $langTitles[$code === 'en' ? 'en' : 'ar'];
                $exists = DB::table('pages_description')
                    ->where('page_id', $pageId)
                    ->where('language_id', $languageId)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('pages_description')->insert($this->rowForTable('pages_description', [
                    'page_id' => $pageId,
                    'language_id' => $languageId,
                    'title' => $title,
                    'sub_title' => null,
                    'short_description' => null,
                    'description' => $pageId === 1 ? '<p>Welcome to Cookster.</p>' : '<p>Content pending.</p>',
                    'meta_title' => $title.' | Cookster',
                    'meta_description' => null,
                    'meta_keywords' => null,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // --- 5) Video / report categories (type 1 & 2) — stable IDs for idempotent runs ---
        // `categories.system_id` is unique: remove legacy rows from earlier seeds so upsert by id works.
        foreach ([1, 2] as $sysId) {
            $stale = DB::table('categories')
                ->where('system_id', $sysId)
                ->whereNotIn('id', [self::CATEGORY_TYPE1_ID, self::CATEGORY_TYPE2_ID])
                ->pluck('id');
            foreach ($stale as $oldId) {
                DB::table('categories_description')->where('category_id', $oldId)->delete();
                DB::table('categories')->where('id', $oldId)->delete();
            }
        }

        foreach (
            [
                self::CATEGORY_TYPE1_ID => ['system_id' => 1, 'type' => 1, 'en' => 'General', 'ar' => 'عام'],
                self::CATEGORY_TYPE2_ID => ['system_id' => 2, 'type' => 2, 'en' => 'General', 'ar' => 'عام'],
            ] as $categoryId => $meta
        ) {
            DB::table('categories')->updateOrInsert(
                ['id' => $categoryId],
                $this->rowForTable('categories', [
                    'id' => $categoryId,
                    'system_id' => $meta['system_id'],
                    'type' => $meta['type'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
            foreach ([1 => $meta['en'], 2 => $meta['ar']] as $languageId => $name) {
                if (DB::table('categories_description')
                    ->where('category_id', $categoryId)
                    ->where('language_id', $languageId)
                    ->exists()) {
                    continue;
                }
                DB::table('categories_description')->insert($this->rowForTable('categories_description', [
                    'category_id' => $categoryId,
                    'language_id' => $languageId,
                    'name' => $name,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $this->seedRegistrationAndCmsBootstrap($now);

        $this->command?->info('Recovery hydration complete. Log in as admin@cookster.org (set RECOVERY_ADMIN_PASSWORD or change default password).');
    }

    /**
     * Mobile registration_settings, web CMS lists, and related lookups.
     */
    private function seedRegistrationAndCmsBootstrap(\DateTimeInterface $now): void
    {
        if (! Schema::hasTable('entities')) {
            return;
        }

        // --- Entities (account types). Chef was status=0 in legacy dump — enable so it appears in the app. ---
        $entities = [
            [1, 'Personal', 'شخصي', 'Watch, engage, and share your favorite food moments.', 'شاهد، وتفاعل، وشارك لحظاتك المفضلة مع الطعام.', 1, 0, 0, 1],
            [2, 'Business', 'تجارة', 'Promote your restaurant, food brand, or catering service with engaging videos.', 'روّج لمطعمك أو علامتك التجارية أو خدمة تقديم الطعام من خلال فيديوهات جذابة.', 2, 1, 0, 1],
            [3, 'Chef', 'الشيف', 'Create and share your culinary craft.', 'أنشئ وشارك حرفتك الطهوية.', 3, 0, 0, 1],
            [8, 'Sponsored', 'مُموَّل', 'Advertise your brand, product, or service through sponsored ads.', 'روّج لعلامتك التجارية أو منتجك أو خدمتك من خلال إعلانات برعاية.', 4, 0, 1, 1],
        ];
        foreach ($entities as $e) {
            DB::table('entities')->updateOrInsert(
                ['id' => $e[0]],
                $this->rowForTable('entities', [
                    'id' => $e[0],
                    'name' => $e[1],
                    'name_ar' => $e[2],
                    'description' => $e[3],
                    'description_ar' => $e[4],
                    'sort_order' => $e[5],
                    'subscription_required' => $e[6],
                    'is_sponsored' => $e[7],
                    'status' => $e[8],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        // --- Generic keys 1–5 + descriptions (en/ar) ---
        if (Schema::hasTable('generic_keys')) {
            foreach ([1, 2, 3, 4, 5] as $kid) {
                DB::table('generic_keys')->updateOrInsert(
                    ['id' => $kid],
                    $this->rowForTable('generic_keys', [
                        'id' => $kid,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        $keyDesc = [
            [1, 1, 1, 'Business Types'], [2, 1, 2, 'أنواع الأعمال'],
            [3, 2, 1, 'Video Types'], [4, 2, 2, 'أنواع الفيديو'],
            [5, 3, 1, 'Gender'], [6, 3, 2, 'جنس'],
            [7, 4, 1, 'Type of Account'], [8, 4, 2, 'نوع الحساب'],
            [9, 5, 1, 'Business Types (B2B)'], [10, 5, 2, 'أنواع الأعمال'],
        ];
        if (Schema::hasTable('generic_keys_description')) {
            foreach ($keyDesc as $row) {
                DB::table('generic_keys_description')->updateOrInsert(
                    ['id' => $row[0]],
                    $this->rowForTable('generic_keys_description', [
                        'id' => $row[0],
                        'key_id' => $row[1],
                        'language_id' => $row[2],
                        'name' => $row[3],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        // --- Values: ids 1–15 match cookster.sql (keys 1–4) ---
        if (Schema::hasTable('generic_key_values')) {
            $valueRows = [
                [1, 1], [2, 1], [3, 1], [4, 1], [5, 1], [6, 1],
                [7, 2], [8, 2], [9, 2],
                [10, 3], [11, 3],
                [12, 4], [13, 4], [14, 4], [15, 4],
            ];
            foreach ($valueRows as [$vid, $keyId]) {
                DB::table('generic_key_values')->updateOrInsert(
                    ['id' => $vid],
                    $this->rowForTable('generic_key_values', [
                        'id' => $vid,
                        'key_id' => $keyId,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        $valDesc = [
            [1, 1, 1, 'Restaurant'], [2, 1, 2, 'مطعم'],
            [3, 2, 1, 'Coffee Shop'], [4, 2, 2, 'مقهى'],
            [5, 3, 1, 'Juice Shop'], [6, 3, 2, 'محل عصير'],
            [7, 4, 1, 'Dessert Shop'], [8, 4, 2, 'متجر الحلوى'],
            [9, 5, 1, 'Bakery'], [10, 5, 2, 'مخبز'],
            [11, 6, 1, 'Protective Family'], [12, 6, 2, 'العائلة الواقية'],
            [13, 7, 1, 'Meal'], [14, 7, 2, 'وجبة'],
            [15, 8, 1, 'Drink'], [16, 8, 2, 'شرب'],
            [17, 9, 1, 'Dessert'], [18, 9, 2, 'حَلوَى'],
            [19, 10, 1, 'Male'], [20, 10, 2, 'ذكر'],
            [21, 11, 1, 'Female'], [22, 11, 2, 'أنثى'],
            [23, 12, 1, 'Advertising Agency'], [24, 12, 2, 'وكالة اعلانية'],
            [25, 13, 1, 'Marketing Agency'], [26, 13, 2, 'وكالة التسويق'],
            [27, 14, 1, 'Supply Product'], [28, 14, 2, 'منتج التوريد'],
            [29, 15, 1, 'Others'], [30, 15, 2, 'آخرون'],
        ];
        if (Schema::hasTable('generic_key_values_description')) {
            foreach ($valDesc as $row) {
                DB::table('generic_key_values_description')->updateOrInsert(
                    ['id' => $row[0]],
                    $this->rowForTable('generic_key_values_description', [
                        'id' => $row[0],
                        'value_id' => $row[1],
                        'language_id' => $row[2],
                        'name' => $row[3],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        // --- Subscription packages (Business entity) ---
        if (Schema::hasTable('packages')) {
            $pkgs = [
                ['6ed17a96-bf4d-4c15-928a-3dca33ec7290', 2, 1000, 1],
                ['6478f1f9-a6d2-47df-a5c3-fd74f92f793b', 3, 5000, 6],
                ['9f818d23-fd61-47f0-a13d-13d8881b812b', 4, 10000, 12],
            ];
            foreach ($pkgs as $p) {
                DB::table('packages')->updateOrInsert(
                    ['id' => $p[0]],
                    $this->rowForTable('packages', [
                        'id' => $p[0],
                        'system_id' => $p[1],
                        'amount' => $p[2],
                        'duration' => $p[3],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        if (Schema::hasTable('packages_description')) {
            $pkgDesc = [
                ['46a4ffcc-ac14-46bf-89d6-8ea5ed9218f3', '6ed17a96-bf4d-4c15-928a-3dca33ec7290', 1, 'Basic Plan', '<p>Business subscription — update copy in admin.</p>'],
                ['f38fc4b5-d77a-44bc-83c6-1212a296ab60', '6ed17a96-bf4d-4c15-928a-3dca33ec7290', 2, 'الخطة الأساسية', '<p>اشتراك تجاري — حرّر النص من لوحة التحكم.</p>'],
                ['f7d7fc92-2d50-44f8-80ff-240e7f1c183c', '6478f1f9-a6d2-47df-a5c3-fd74f92f793b', 1, 'Executive Plan', '<p>Business subscription — update copy in admin.</p>'],
                ['a20065ec-e495-48ae-9501-46191578803b', '6478f1f9-a6d2-47df-a5c3-fd74f92f793b', 2, 'الخطة التنفيذية', '<p>اشتراك تجاري — حرّر النص من لوحة التحكم.</p>'],
                ['953f3d59-db9a-460e-ba40-3c76b5ce6ca7', '9f818d23-fd61-47f0-a13d-13d8881b812b', 1, 'Premium Plan', '<p>Business subscription — update copy in admin.</p>'],
                ['b4119a09-3698-4b6e-bf46-0a7a5ec22784', '9f818d23-fd61-47f0-a13d-13d8881b812b', 2, 'الخطة المميزة', '<p>اشتراك تجاري — حرّر النص من لوحة التحكم.</p>'],
            ];
            foreach ($pkgDesc as $r) {
                DB::table('packages_description')->updateOrInsert(
                    ['id' => $r[0]],
                    $this->rowForTable('packages_description', [
                        'id' => $r[0],
                        'package_id' => $r[1],
                        'language_id' => $r[2],
                        'title' => $r[3],
                        'description' => $r[4],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        // --- Home banner (image path: add file under storage/app/public/banners/ or replace in admin) ---
        if (Schema::hasTable('banners')) {
            DB::table('banners')->updateOrInsert(
                ['id' => 2],
                $this->rowForTable('banners', [
                    'id' => 2,
                    'image' => '',
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
        if (Schema::hasTable('banners_description')) {
            foreach (
                [
                    [5, 2, 1, 'Discover, Share, Enjoy Food Videos', 'The ultimate platform for food lovers to watch, create, and share short food videos.'],
                    [6, 2, 2, 'اكتشف وشارك واستمتع بفيديوهات الطعام.', 'المنصة المثالية لعشاق الطعام لمشاهدة وإنشاء ومشاركة فيديوهات الطعام القصيرة.'],
                ] as $b
            ) {
                DB::table('banners_description')->updateOrInsert(
                    ['id' => $b[0]],
                    $this->rowForTable('banners_description', [
                        'id' => $b[0],
                        'banner_id' => $b[1],
                        'language_id' => $b[2],
                        'title' => $b[3],
                        'sub_title' => $b[4],
                        'short_description' => null,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        // --- Onboarding screens (started_screens API) ---
        if (Schema::hasTable('screens')) {
            foreach ([2, 3, 4] as $sid) {
                DB::table('screens')->updateOrInsert(
                    ['id' => $sid],
                    $this->rowForTable('screens', [
                        'id' => $sid,
                        'image' => '',
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }
        if (Schema::hasTable('screens_description')) {
            $screenTxt = 'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout.';
            $screenAr = 'من الثابت أن المحتوى المقروء لصفحة ما سيشتت انتباه القارئ عند النظر إلى شكل توضع الفقرات.';
            foreach ([2, 3, 4] as $sid) {
                foreach ([1 => $screenTxt, 2 => $screenAr] as $lid => $txt) {
                    $descId = ($sid - 2) * 2 + $lid + 2;
                    DB::table('screens_description')->updateOrInsert(
                        ['id' => $descId],
                        $this->rowForTable('screens_description', [
                            'id' => $descId,
                            'screen_id' => $sid,
                            'language_id' => $lid,
                            'title' => $lid === 1 ? 'Welcome to Cookster' : 'مرحبًا بك في كوكستر',
                            'sub_title' => null,
                            'short_description' => $txt,
                            'status' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                    );
                }
            }
        }

        // --- How it works (frontend) ---
        if (Schema::hasTable('works')) {
            foreach ([2 => 1, 3 => 2, 4 => 3, 5 => 4] as $wid => $ord) {
                DB::table('works')->updateOrInsert(
                    ['id' => $wid],
                    $this->rowForTable('works', [
                        'id' => $wid,
                        'sort_order' => $ord,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }
        if (Schema::hasTable('works_description')) {
            $steps = [
                [3, 2, 1, '1', 'Sign Up & Choose Your Role', '<ul><li>Register as Personal, Business, Chef, or Sponsored.</li><li>Create your profile.</li><li>Explore trending food videos.</li></ul>'],
                [4, 2, 2, '1', 'سجّل واختر دورك', '<ul><li>سجّل كفرد، أو نشاط تجاري، أو شيف، أو برعاية.</li><li>أنشئ ملفك الشخصي.</li><li>استكشف فيديوهات الطعام الرائجة.</li></ul>'],
                [5, 3, 1, '2', 'Watch & Engage', '<ul><li>Swipe through food content.</li><li>Like, comment, and share.</li><li>Follow creators and businesses.</li></ul>'],
                [6, 3, 2, '2', 'شاهد وتفاعل', '<ul><li>تصفّح محتوى الطعام.</li><li>أعجب، علّق، وشارك.</li><li>تابع صانعي المحتوى.</li></ul>'],
                [7, 4, 1, '3', 'Create & Upload Videos', '<ul><li>Record and upload food videos.</li><li>Grow your audience.</li></ul>'],
                [8, 4, 2, '3', 'أنشئ وارفع الفيديوهات', '<ul><li>سجّل وارفع فيديوهات الطعام.</li><li>وسّع جمهورك.</li></ul>'],
                [9, 5, 1, '4', 'Grow & Connect', '<ul><li>Build your audience.</li><li>Collaborate with brands.</li></ul>'],
                [10, 5, 2, '4', 'نمُ وتواصل', '<ul><li>كوّن جمهورك.</li><li>تعاون مع العلامات التجارية.</li></ul>'],
            ];
            foreach ($steps as $s) {
                DB::table('works_description')->updateOrInsert(
                    ['id' => $s[0]],
                    $this->rowForTable('works_description', [
                        'id' => $s[0],
                        'work_id' => $s[1],
                        'language_id' => $s[2],
                        'number' => $s[3],
                        'title' => $s[4],
                        'description' => $s[5],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        // --- Blog categories (empty blogs OK; list UI needs categories) ---
        if (Schema::hasTable('blogcategories')) {
            foreach ([1, 2, 3, 4, 5] as $bcid) {
                DB::table('blogcategories')->updateOrInsert(
                    ['id' => $bcid],
                    $this->rowForTable('blogcategories', [
                        'id' => $bcid,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }
        if (Schema::hasTable('blogcategories_description')) {
            $bcd = [
                [1, 1, 1, 'Restaurants and Food', 'Restaurants and Food | Cookster', 'Restaurants and Food | Cookster', 'food,restaurant,recipe'],
                [2, 1, 2, 'المطاعم والطعام', 'المطاعم والطعام | Cookster', null, null],
                [3, 2, 1, 'Chefs and Cooks', null, null, null],
                [4, 2, 2, 'الطهاة والطباخون', null, null, null],
                [5, 3, 1, 'Home Businesses', null, null, null],
                [6, 3, 2, 'الأعمال المنزلية', null, null, null],
                [7, 4, 1, 'Home Recipes', null, null, null],
                [8, 4, 2, 'وصفات منزلية', null, null, null],
                [9, 5, 1, 'Cafes and Entertainment', null, null, null],
                [10, 5, 2, 'المقاهي والترفيه', null, null, null],
            ];
            foreach ($bcd as $r) {
                DB::table('blogcategories_description')->updateOrInsert(
                    ['id' => $r[0]],
                    $this->rowForTable('blogcategories_description', [
                        'id' => $r[0],
                        'blogcategory_id' => $r[1],
                        'language_id' => $r[2],
                        'title' => $r[3],
                        'meta_title' => $r[4],
                        'meta_description' => $r[5],
                        'meta_keywords' => $r[6],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        // --- Countries & states: registration_settings hard-codes states for country_id 194 (Saudi Arabia). ---
        if (Schema::hasTable('countries')) {
            DB::table('countries')->updateOrInsert(
                ['id' => 194],
                $this->rowForTable('countries', [
                    'id' => 194,
                    'name' => 'Saudi Arabia',
                    'iso3' => 'SAU',
                    'numeric_code' => '682',
                    'iso2' => 'SA',
                    'phonecode' => '966',
                    'capital' => 'Riyadh',
                    'currency' => 'SAR',
                    'currency_name' => 'Saudi riyal',
                    'currency_symbol' => '﷼',
                    'tld' => '.sa',
                    'native' => 'المملكة العربية السعودية',
                    'region' => 'Asia',
                    'subregion' => 'Western Asia',
                    'timezones' => '[{"zoneName":"Asia/Riyadh","gmtOffset":10800,"gmtOffsetName":"UTC+03:00","abbreviation":"AST","tzName":"Arabia Standard Time"}]',
                    'translations' => '{}',
                    'latitude' => 25.0,
                    'longitude' => 45.0,
                    'emoji' => '🇸🇦',
                    'emojiU' => 'U+1F1F8 U+1F1E6',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'flag' => 1,
                    'wikiDataId' => 'Q851',
                    'status' => 1,
                ])
            );
            DB::table('countries')->updateOrInsert(
                ['id' => 231],
                $this->rowForTable('countries', [
                    'id' => 231,
                    'name' => 'United Arab Emirates',
                    'iso3' => 'ARE',
                    'numeric_code' => '784',
                    'iso2' => 'AE',
                    'phonecode' => '971',
                    'capital' => 'Abu Dhabi',
                    'currency' => 'AED',
                    'currency_name' => 'United Arab Emirates dirham',
                    'currency_symbol' => 'د.إ',
                    'tld' => '.ae',
                    'native' => 'دولة الإمارات العربية المتحدة',
                    'region' => 'Asia',
                    'subregion' => 'Western Asia',
                    'timezones' => '[{"zoneName":"Asia/Dubai","gmtOffset":14400,"gmtOffsetName":"UTC+04:00","abbreviation":"GST","tzName":"Gulf Standard Time"}]',
                    'translations' => '{}',
                    'latitude' => 24.0,
                    'longitude' => 54.0,
                    'emoji' => '🇦🇪',
                    'emojiU' => 'U+1F1E6 U+1F1EA',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'flag' => 1,
                    'wikiDataId' => 'Q878',
                    'status' => 1,
                ])
            );
        }

        if (Schema::hasTable('states')) {
            // Saudi regions + UAE emirates (IDs match cookster.sql / GeoNames-style dumps).
            $regionRows = [
                [2849, 'Riyadh', 194, 'SA', '10', '01', null, 22.75543850, 46.20915470],
                [2850, 'Makkah', 194, 'SA', '14', '02', null, 21.52355840, 41.91964710],
                [2851, 'Al Madinah', 194, 'SA', '05', '03', null, 24.84039770, 39.32062410],
                [2852, 'Tabuk', 194, 'SA', '19', '07', null, 28.24533350, 37.63866220],
                [2853, 'Asir', 194, 'SA', '11', '14', null, 19.09690620, 42.86378750],
                [2854, 'Northern Borders', 194, 'SA', '15', '08', null, 30.07991620, 42.86378750],
                [2855, 'Ha\'il', 194, 'SA', '13', '06', null, 27.70761430, 41.91964710],
                [2857, 'Al Jawf', 194, 'SA', '20', '12', null, 29.88735600, 39.32062410],
                [2858, 'Jizan', 194, 'SA', '17', '09', null, 17.17381760, 42.70761070],
                [2859, 'Al Bahah', 194, 'SA', '02', '11', null, 20.27227390, 41.44125100],
                [2860, 'Najran', 194, 'SA', '16', '10', null, 18.35146640, 45.60071080],
                [2861, 'Al-Qassim', 194, 'SA', '08', '05', null, 26.20782600, 43.48373800],
                [3390, 'Sharjah Emirate', 231, 'AE', '06', 'SH', null, 25.07539740, 55.75784030],
                [3391, 'Dubai', 231, 'AE', '03', 'DU', null, 25.20484930, 55.27078280],
                [3392, 'Umm al-Quwain', 231, 'AE', '07', 'UQ', null, 25.54263240, 55.54753480],
                [3393, 'Fujairah', 231, 'AE', '04', 'FU', null, 25.12880990, 56.32648490],
                [3394, 'Ras al-Khaimah', 231, 'AE', '05', 'RK', null, 25.67413430, 55.98041730],
                [3395, 'Ajman Emirate', 231, 'AE', '02', 'AJ', null, 25.40521650, 55.51364330],
                [3396, 'Abu Dhabi Emirate', 231, 'AE', '01', 'AZ', null, 24.45388400, 54.37734380],
            ];
            foreach ($regionRows as $s) {
                DB::table('states')->updateOrInsert(
                    ['id' => $s[0]],
                    $this->rowForTable('states', [
                        'id' => $s[0],
                        'name' => $s[1],
                        'country_id' => $s[2],
                        'country_code' => $s[3],
                        'fips_code' => $s[4],
                        'iso2' => $s[5],
                        'type' => $s[6],
                        'latitude' => $s[7],
                        'longitude' => $s[8],
                        'created_at' => $now,
                        'updated_at' => $now,
                        'flag' => 1,
                        'wikiDataId' => null,
                    ])
                );
            }
        }

        // Cities: admin AJAX loads cities by state_id or by country_id (city groups). Recovery had states but no cities.
        if (Schema::hasTable('cities')) {
            $cityRows = [
                // Saudi Arabia (ids from cookster.sql for stable references)
                [102874, 'Riyadh', 2849, '01', 194, 'SA', 24.68773000, 46.72185000],
                [102826, 'Al Kharj', 2849, '01', 194, 'SA', 24.15541000, 47.33457000],
                [102858, 'Jeddah', 2850, '02', 194, 'SA', 21.54238000, 39.19797000],
                [102864, 'Mecca', 2850, '02', 194, 'SA', 21.42664000, 39.82563000],
                [102884, 'Ta\'if', 2850, '02', 194, 'SA', 21.27028000, 40.41583000],
                [102865, 'Medina', 2851, '03', 194, 'SA', 24.46861000, 39.61417000],
                [102892, 'Yanbu', 2851, '03', 194, 'SA', 24.08954000, 38.06180000],
                [150108, 'Dammam', 2851, '03', 194, 'SA', 26.42070000, 50.08880000],
                [150107, 'Khobar', 2851, '03', 194, 'SA', 26.21720000, 50.19710000],
                [150109, 'Dhahran', 2851, '03', 194, 'SA', 26.23610000, 50.03930000],
                [102990, 'Tabuk', 2852, '07', 194, 'SA', 28.39980000, 36.57151000],
                [102804, 'Abha', 2853, '14', 194, 'SA', 18.21639000, 42.50528000],
                [102861, 'Khamis Mushait', 2853, '14', 194, 'SA', 18.30000000, 42.73333000],
                [102842, 'Arar', 2854, '08', 194, 'SA', 30.97531000, 41.03808000],
                [102856, 'Ha\'il', 2855, '06', 194, 'SA', 27.52188000, 41.69073000],
                [102877, 'Sakakah', 2857, '12', 194, 'SA', 29.96974000, 40.20641000],
                [102872, 'Qurayyat', 2857, '12', 194, 'SA', 31.33176000, 37.34282000],
                [102859, 'Jizan', 2858, '09', 194, 'SA', 16.88917000, 42.55111000],
                [102815, 'Al Bahah', 2859, '11', 194, 'SA', 20.01288000, 41.46767000],
                [102869, 'Najran', 2860, '10', 194, 'SA', 17.49326000, 44.12766000],
                [102850, 'Buraydah', 2861, '05', 194, 'SA', 26.32599000, 43.97497000],
                [102841, 'Ar Rass', 2861, '05', 194, 'SA', 25.86944000, 43.49730000],
                // United Arab Emirates
                [32, 'Dubai', 3391, 'DU', 231, 'AE', 25.06570000, 55.17128000],
                [12, 'Abu Dhabi Municipality', 3396, 'AZ', 231, 'AE', 24.41361000, 54.43295000],
                [16, 'Al Ain City', 3396, 'AZ', 231, 'AE', 24.19167000, 55.76056000],
                [14, 'Ajman', 3395, 'AJ', 231, 'AE', 25.40328000, 55.52341000],
                [46, 'Sharjah', 3390, 'SH', 231, 'AE', 25.33737000, 55.41206000],
                [43, 'Ras Al Khaimah', 3394, 'RK', 231, 'AE', 25.46116000, 56.04058000],
                [20, 'Al Fujairah City', 3393, 'FU', 231, 'AE', 25.11641000, 56.34141000],
                [48, 'Umm Al Quwain City', 3392, 'UQ', 231, 'AE', 25.56473000, 55.55517000],
            ];
            foreach ($cityRows as $c) {
                DB::table('cities')->updateOrInsert(
                    ['id' => $c[0]],
                    $this->rowForTable('cities', [
                        'id' => $c[0],
                        'name' => $c[1],
                        'state_id' => $c[2],
                        'state_code' => $c[3],
                        'country_id' => $c[4],
                        'country_code' => $c[5],
                        'latitude' => $c[6],
                        'longitude' => $c[7],
                        'created_at' => $now,
                        'updated_at' => $now,
                        'flag' => 1,
                        'wikiDataId' => null,
                    ])
                );
            }
        }
    }

    /**
     * Permission names used by admin middleware (@see app/Http/Controllers) and sidebar (layouts/app.blade.php).
     */
    public function ensureAllAdminPermissionsExist(string $guard): void
    {
        $crud = static function (string $prefix): array {
            return ["{$prefix}-list", "{$prefix}-create", "{$prefix}-edit", "{$prefix}-delete"];
        };

        $names = array_merge(
            $crud('users'),
            $crud('role'),
            $crud('generic-keys'),
            $crud('generic-key-values'),
            $crud('categories'),
            $crud('screens'),
            $crud('advertisements'),
            $crud('packages'),
            $crud('cities-groups'),
            $crud('audios'),
            $crud('pages'),
            $crud('works'),
            $crud('blogcategories'),
            $crud('blogs'),
            $crud('banners'),
            $crud('settings'),
            [
                'personal-accounts',
                'business-accounts',
                'chef-accounts',
                'sponsored-accounts',
                'videos-list',
                'user-reviews-list',
                'user-payments-list',
                'notifications-list',
                'notifications-create',
            ],
            $crud('species'),
        );

        foreach (array_unique($names) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }
    }
}
