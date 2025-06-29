<?php

namespace Dotxdd\ColoringBook;

class Segmenter
{
    /**
     * Flood fill segmentation of the image into color areas.
     */
    public static function segment($width, $height, $getColorAt)
    {
        $labels = array_fill(0, $height, array_fill(0, $width, -1));
        $areas = [];
        $label = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($labels[$y][$x] !== -1) continue;

                $color = $getColorAt($x, $y);
                $queue = [[$x, $y]];
                $areaPixels = [];

                while ($queue) {
                    list($cx, $cy) = array_pop($queue);
                    if ($cx < 0 || $cy < 0 || $cx >= $width || $cy >= $height) continue;
                    if ($labels[$cy][$cx] !== -1) continue;
                    if ($getColorAt($cx, $cy) !== $color) continue;

                    $labels[$cy][$cx] = $label;
                    $areaPixels[] = [$cx, $cy];

                    $queue[] = [$cx + 1, $cy];
                    $queue[] = [$cx - 1, $cy];
                    $queue[] = [$cx, $cy + 1];
                    $queue[] = [$cx, $cy - 1];
                }

                if (count($areaPixels) > 0) {
                    $areas[$label] = [
                        'color' => $color,
                        'pixels' => $areaPixels,
                    ];
                    $label++;
                }
            }
        }

        return [$labels, $areas];
    }
} 
