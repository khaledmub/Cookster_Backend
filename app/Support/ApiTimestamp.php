<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;

class ApiTimestamp
{
  /**
   * Mobile contract: ISO 8601 UTC with microseconds (e.g. 2024-12-13T10:57:49.963000Z).
   */
  public static function format(mixed $value): ?string
  {
    if ($value === null || $value === '') {
      return null;
    }

    if ($value instanceof CarbonInterface) {
      return $value->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    if ($value instanceof DateTimeInterface) {
      return $value->format('Y-m-d\TH:i:s.u\Z');
    }

    try {
      return \Illuminate\Support\Carbon::parse((string) $value)->utc()->format('Y-m-d\TH:i:s.u\Z');
    } catch (\Throwable) {
      return null;
    }
  }
}
