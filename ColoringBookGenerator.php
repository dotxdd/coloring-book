<?php

namespace ColoringBook;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use ColoringBook\Interfaces\ColoringBookGeneratorInterface;
use ColoringBook\Segmenter;

class ColoringBookGenerator implements ColoringBookGeneratorInterface
{
    private $image;
    private $width;
    private $height;
    private $contourThreshold = 10;
    private $maxColors = 30;
    private $imageManager;
    private $reducedColors = [];

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    public function loadImage($imagePath)
    {
        $this->image = $this->imageManager->read($imagePath);
        $this->width = $this->image->width();
        $this->height = $this->image->height();
        return $this;
    }

    public function setContourThreshold($threshold)
    {
        $this->contourThreshold = max(1, min(100, $threshold));
        return $this;
    }

    public function setMaxColors($maxColors)
    {
        $this->maxColors = max(10, min(50, $maxColors));
        return $this;
    }

    public function getColorList()
    {
        return $this->reducedColors;
    }

    /**
     * Main function: generates a child-friendly coloring book with large, smooth color areas and clear contours.
     */
    public function generateChildFriendlyColoringBook($numColors = 10, $blurRadius = 8, $minAreaPercent = 0.015)
    {
        if (!$this->image) {
            throw new \Exception('Load image first using loadImage()');
        }

        // 1. Blur the image to merge small details
        $blurred = clone $this->image;
        if (method_exists($blurred, 'blur')) {
            $blurred->blur($blurRadius);
        }

        // 2. Color reduction (k-means)
        $colors = $this->extractColorsFromImage($blurred);
        $palette = $this->performKMeansColorClustering($colors, $numColors);
        foreach ($palette as &$color) {
            $color['hex'] = sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
        }
        unset($color);
        $this->reducedColors = $palette;

        // 3. Assign each pixel to the nearest palette color
        $segmented = $this->imageManager->create($this->width, $this->height);
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $pixel = $blurred->pickColor($x, $y);
                $nearest = $this->findNearestColorInPalette($pixel, $palette);
                $segmented->drawPixel($x, $y, $nearest['hex']);
            }
        }

        // 4. Segment color areas (flood fill)
        $getColorAt = function($x, $y) use ($segmented) {
            $pixel = $segmented->pickColor($x, $y);
            return sprintf('#%02x%02x%02x', $pixel->red()->toInt(), $pixel->green()->toInt(), $pixel->blue()->toInt());
        };
        list($labels, $areas) = Segmenter::segment($this->width, $this->height, $getColorAt);

        // 5. Merge small areas with neighbors
        $totalPixels = $this->width * $this->height;
        $minArea = intval($totalPixels * $minAreaPercent);

        do {
            $changed = false;
            foreach ($areas as $label => $area) {
                if (count($area['pixels']) >= $minArea || count($area['pixels']) === 0) continue;

                // Find neighbors
                $neighborLabels = [];
                foreach ($area['pixels'] as [$x, $y]) {
                    foreach ([[-1,0],[1,0],[0,-1],[0,1]] as [$dx, $dy]) {
                        $nx = $x + $dx; $ny = $y + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $this->width || $ny >= $this->height) continue;
                        $neighborLabel = $labels[$ny][$nx];
                        if ($neighborLabel !== $label && isset($areas[$neighborLabel]) && count($areas[$neighborLabel]['pixels']) >= $minArea) {
                            $neighborLabels[$neighborLabel] = ($neighborLabels[$neighborLabel] ?? 0) + 1;
                        }
                    }
                }
                if ($neighborLabels) {
                    arsort($neighborLabels);
                    $mainLabel = array_key_first($neighborLabels);
                    foreach ($area['pixels'] as [$x, $y]) {
                        $labels[$y][$x] = $mainLabel;
                        $areas[$mainLabel]['pixels'][] = [$x, $y];
                    }
                    $areas[$label]['pixels'] = [];
                    $changed = true;
                }
            }
            $areas = array_filter($areas, function($a) {
                return count($a['pixels']) > 0;
            });
        } while ($changed);

        // 6. Draw contours and numbers
        $outline = $this->imageManager->create($this->width, $this->height)->fill('ffffff');
        $colorNumbers = [];
        foreach ($palette as $i => $color) {
            $colorNumbers[$color['hex']] = $i + 1;
        }

        foreach ($areas as $area) {
            if (count($area['pixels']) < 2) continue;

            $this->drawSmoothContour($outline, $area['pixels']);

            $center = $this->findAreaCenter($area['pixels']);
            $colorNum = $colorNumbers[$area['color']] ?? 1;
            $outline->text(
                (string)$colorNum,
                $center[0], $center[1],
                function ($font) {
                    $font->size(18);
                    $font->color('000000');
                    $font->align('center');
                    $font->valign('center');
                }
            );
        }

        return $outline;
    }

    public function saveColorPalette($outputPath)
    {
        if (empty($this->reducedColors)) {
            throw new \Exception('No colors available. Generate coloring book first.');
        }
        
        $palette = [];
        foreach ($this->reducedColors as $index => $color) {
            $palette[] = [
                'number' => $index + 1,
                'hex' => $color['hex'],
                'rgb' => [
                    'r' => $color['r'],
                    'g' => $color['g'],
                    'b' => $color['b']
                ]
            ];
        }
        
        $jsonData = [
            'total_colors' => count($palette),
            'colors' => $palette,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($outputPath, json_encode($jsonData, JSON_PRETTY_PRINT));
        return $this;
    }

    // --- Helper methods below ---

    private function extractColorsFromImage($image)
    {
        $colors = [];
        $width = $image->width();
        $height = $image->height();
        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                $pixel = $image->pickColor($x, $y);
                $colors[] = [
                    'r' => $pixel->red()->toInt(),
                    'g' => $pixel->green()->toInt(),
                    'b' => $pixel->blue()->toInt(),
                    'count' => 1
                ];
            }
        }
        return $colors;
    }

    private function findNearestColorInPalette($pixel, $palette)
    {
        $r = $pixel->red()->toInt();
        $g = $pixel->green()->toInt();
        $b = $pixel->blue()->toInt();
        $minDist = PHP_INT_MAX;
        $nearest = $palette[0];
        foreach ($palette as $color) {
            $dist = $this->calculateColorDistance($r, $g, $b, $color['r'], $color['g'], $color['b']);
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $color;
            }
        }
        return $nearest;
    }

    private function findAreaCenter($pixels)
    {
        $sumX = 0; $sumY = 0;
        foreach ($pixels as [$x, $y]) {
            $sumX += $x; $sumY += $y;
        }
        $n = count($pixels);
        return [intval($sumX / $n), intval($sumY / $n)];
    }

    private function drawSmoothContour($image, $pixels)
    {
        $map = [];
        foreach ($pixels as [$x, $y]) {
            $map["$x,$y"] = true;
        }
        foreach ($pixels as [$x, $y]) {
            foreach ([[-1,0],[1,0],[0,-1],[0,1]] as [$dx,$dy]) {
                $nx = $x + $dx; $ny = $y + $dy;
                if (!isset($map["$nx,$ny"])) {
                    $image->drawPixel($x, $y, '000000');
                }
            }
        }
    }

    private function calculateColorDistance($r1, $g1, $b1, $r2, $g2, $b2)
    {
        $rWeight = 2;
        $gWeight = 4;
        $bWeight = 3;
        return sqrt(
            $rWeight * pow($r1 - $r2, 2) +
            $gWeight * pow($g1 - $g2, 2) +
            $bWeight * pow($b1 - $b2, 2)
        );
    }

    private function performKMeansColorClustering($colors, $k)
    {
        if (count($colors) <= $k) {
            return array_slice($colors, 0, $k);
        }
        $centroids = array_slice($colors, 0, $k);
        $maxIterations = 20;
        $tolerance = 1.0;
        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $clusters = array_fill(0, $k, []);
            $oldCentroids = $centroids;
            foreach ($colors as $color) {
                $minDistance = PHP_INT_MAX;
                $closestCentroid = 0;
                for ($i = 0; $i < $k; $i++) {
                    $distance = $this->calculateColorDistance(
                        $color['r'], $color['g'], $color['b'],
                        $centroids[$i]['r'], $centroids[$i]['g'], $centroids[$i]['b']
                    );
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $closestCentroid = $i;
                    }
                }
                $clusters[$closestCentroid][] = $color;
            }
            for ($i = 0; $i < $k; $i++) {
                if (!empty($clusters[$i])) {
                    $totalR = $totalG = $totalB = $totalWeight = 0;
                    foreach ($clusters[$i] as $color) {
                        $weight = $color['count'] ?? 1;
                        $totalR += $color['r'] * $weight;
                        $totalG += $color['g'] * $weight;
                        $totalB += $color['b'] * $weight;
                        $totalWeight += $weight;
                    }
                    $centroids[$i] = [
                        'r' => round($totalR / $totalWeight),
                        'g' => round($totalG / $totalWeight),
                        'b' => round($totalB / $totalWeight),
                        'count' => $totalWeight
                    ];
                }
            }
            $converged = true;
            for ($i = 0; $i < $k; $i++) {
                $distance = $this->calculateColorDistance(
                    $oldCentroids[$i]['r'], $oldCentroids[$i]['g'], $oldCentroids[$i]['b'],
                    $centroids[$i]['r'], $centroids[$i]['g'], $centroids[$i]['b']
                );
                if ($distance > $tolerance) {
                    $converged = false;
                    break;
                }
            }
            if ($converged) {
                break;
            }
        }
        usort($centroids, function($a, $b) {
            return ($b['count'] ?? 0) - ($a['count'] ?? 0);
        });
        return $centroids;
    }
}