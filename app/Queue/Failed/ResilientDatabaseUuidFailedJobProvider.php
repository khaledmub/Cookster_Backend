<?php

namespace App\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

class ResilientDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     * Recording a failure must never throw. The worker deletes the job and then fires
     * the failed event, so an exception here escapes before the queue is told the job
     * is done, leaving it reserved to replay on every retry_after window forever.
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $uuid = json_decode($payload, true)['uuid'] ?? null;

        $attributes = [
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
            'failed_at' => Date::now(),
        ];

        try {
            if ($uuid === null) {
                $this->getTable()->insert($attributes + ['uuid' => (string) Str::uuid()]);
            } else {
                $this->getTable()->updateOrInsert(['uuid' => $uuid], $attributes);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $uuid;
    }
}
