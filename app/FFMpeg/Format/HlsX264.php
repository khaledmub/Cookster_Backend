<?php

namespace App\FFMpeg\Format;

use FFMpeg\Format\Video\X264;

/**
 * X264 format that forwards setAdditionalParameters() to FFmpeg via getExtraParams().
 */
class HlsX264 extends X264
{
    public function getExtraParams(): array
    {
        return $this->getAdditionalParameters() ?? [];
    }
}
