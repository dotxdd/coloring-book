# Coloring Book Generator

PHP library for generating coloring book pages from images.

## Installation

```bash
composer require dotxdd/coloring-book
```

## Basic Usage

```php
use Dotxdd\ColoringBook\ColoringBookGenerator;

$generator = new ColoringBookGenerator();
$result = $generator->generate('input.jpg', 'output.png');

if ($result) {
    echo "Coloring book generated successfully!";
}
```

## Advanced Usage

### Laravel Console Command Example

Here's how to use the library in a Laravel console command with advanced configuration:

```php
use Dotxdd\ColoringBook\ColoringBookGenerator;

class GenerateColoringBook extends Command
{
    protected $signature = 'coloring:generate 
                            {--input=test.jpg : Input image filename}
                            {--output=coloring_book.png : Output coloring book filename}
                            {--colors-json=colors.json : Output colors JSON filename}
                            {--threshold=30 : Contour detection threshold (1-100)}
                            {--colors=30 : Maximum number of colors (10-50)}
                            {--blur=4 : Blur radius}
                            {--minarea=0.015 : Minimum area percent for merging (e.g. 0.015 = 1.5%)}';

    public function handle()
    {
        $inputFile = $this->option('input');
        $outputFile = $this->option('output');
        $colorsJsonFile = $this->option('colors-json');
        
        $threshold = (int) $this->option('threshold');
        $maxColors = (int) $this->option('colors');
        $blur = (int) $this->option('blur');
        $minAreaPercent = (float) $this->option('minarea');

        $generator = new ColoringBookGenerator();
        $generator->setContourThreshold($threshold)
                  ->setMaxColors($maxColors)
                  ->loadImage($inputFile);

        // Generate coloring book with advanced settings
        $image = $generator->generateChildFriendlyColoringBook($maxColors, $blur, $minAreaPercent);
        
        // Save the generated image
        $result = $image->save($outputFile);
        
        // Save color palette as JSON
        $generator->saveColorPalette($colorsJsonFile);
        
        if ($result) {
            echo "Coloring book generated successfully!";
        }
    }
}
```

### Command Line Usage

```bash
# Basic usage with default settings
php artisan coloring:generate --input=photo.jpg --output=coloring.png

# Advanced usage with custom parameters
php artisan coloring:generate \
    --input=photo.jpg \
    --output=coloring.png \
    --colors-json=palette.json \
    --threshold=25 \
    --colors=20 \
    --blur=3 \
    --minarea=0.02
```

### Configuration Parameters

| Parameter | Default | Range | Description |
|-----------|---------|-------|-------------|
| `threshold` | 30 | 1-100 | Contour detection sensitivity |
| `colors` | 30 | 10-50 | Maximum number of colors to extract |
| `blur` | 4 | 1-10 | Blur radius for smoothing |
| `minarea` | 0.015 | 0.001-0.1 | Minimum area percentage for merging regions |

### Output Files

The library generates two types of output files:

1. **Image file** (PNG) - The coloring book page with outlines
2. **JSON file** - Color palette with RGB values for each region

Example JSON output:
```json
{
    "colors": [
        {"r": 255, "g": 0, "b": 0, "area": 0.15},
        {"r": 0, "g": 255, "b": 0, "area": 0.25},
        {"r": 0, "g": 0, "b": 255, "area": 0.10}
    ]
}
```

## Features

- Convert any image to a coloring book page
- Edge detection and outline generation
- Support for various image formats (JPG, PNG, GIF)
- Customizable output settings
- High-quality output suitable for printing
- Color palette extraction and export
- Advanced contour detection with adjustable threshold
- Region merging with configurable minimum area
- Blur and smoothing options for better results

## Requirements

- PHP 8.2 or higher
- GD extension enabled

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.