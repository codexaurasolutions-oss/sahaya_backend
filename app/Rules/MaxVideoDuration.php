<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

class MaxVideoDuration implements Rule
{
    protected $maxDuration;

    public function __construct($maxDuration)
    {
        $this->maxDuration = $maxDuration;
    }

    public function passes($attribute, $value)
    {
        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open($value->getPathname());
        $duration = $video->getFormat()->get('duration');

        return $duration <= $this->maxDuration;
    }

    public function message()
    {
        return trans("messages.the_video_duration_must_be_less_than_or_equal_to_".$this->maxDuration."_seconds");
    }
}
