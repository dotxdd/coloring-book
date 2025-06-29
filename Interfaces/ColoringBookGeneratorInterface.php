<?php

namespace ColoringBook\Interfaces;

interface ColoringBookGeneratorInterface
{
    public function loadImage($imagePath);
    public function setContourThreshold($threshold);
    public function setMaxColors($maxColors);
    public function generateChildFriendlyColoringBook($numColors = 10, $blurRadius = 8, $minAreaPercent = 0.015);
    public function getColorList();
} 