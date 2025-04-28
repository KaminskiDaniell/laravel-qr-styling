<?php

namespace kaminskidaniell\LaravelQRStyling;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Exception;
use Imagick;
use ImagickDraw;
use ImagickPixel;

class QRCodeStyling
{
    protected $qrCode;
    protected $dotShape = 'square'; // square, dots, rounded
    protected $dotColor = [0, 0, 0];
    protected $backgroundColor = [255, 255, 255];
    protected $logoPath = null;
    protected $logoWidth = 60;
    protected $logoHeight = 60;
    protected $gradientType = null; // linear, radial
    protected $gradientColors = [];
    protected $cornerSquareStyle = 'square'; // square, dots, rounded
    protected $cornerSquareColor = null;
    protected $cornerDotStyle = 'square'; // square, dots, rounded
    protected $cornerDotColor = null;
    protected $width = 300;
    protected $height = 300;
    protected $margin = 10;
    protected $frameStyle = null; // none, standard, circle

    public function __construct($options = [])
    {
        $this->qrCode = new QrCode();
        $this->qrCode->setSize($this->width);
        $this->qrCode->setMargin($this->margin);
        $this->qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
        $this->qrCode->setForegroundColor(new Color(...$this->dotColor));
        $this->qrCode->setBackgroundColor(new Color(...$this->backgroundColor));
        $this->qrCode->setEncoding(new Encoding('UTF-8'));

        // Apply options
        if (!empty($options)) {
            $this->applyOptions($options);
        }
    }

    public function applyOptions($options)
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Re-apply settings that change based on properties
        $this->qrCode->setSize($this->width);
        $this->qrCode->setMargin($this->margin);
        $this->qrCode->setForegroundColor(new Color(...$this->dotColor));
        $this->qrCode->setBackgroundColor(new Color(...$this->backgroundColor));

        return $this;
    }

    public function setData($data)
    {
        $this->qrCode->setData($data);
        return $this;
    }

    public function setDotStyle($shape, $color = null)
    {
        $this->dotShape = $shape;
        if ($color !== null) {
            $this->dotColor = $color;
            $this->qrCode->setForegroundColor(new Color(...$this->dotColor));
        }
        return $this;
    }

    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;
        $this->qrCode->setBackgroundColor(new Color(...$this->backgroundColor));
        return $this;
    }

    public function setGradient($type, $colors)
    {
        $this->gradientType = $type;
        $this->gradientColors = $colors;
        return $this;
    }

    public function setLogo($path, $width = 60, $height = 60)
    {
        $this->logoPath = $path;
        $this->logoWidth = $width;
        $this->logoHeight = $height;
        return $this;
    }

    public function setCornerSquareStyle($style, $color = null)
    {
        $this->cornerSquareStyle = $style;
        if ($color !== null) {
            $this->cornerSquareColor = $color;
        }
        return $this;
    }

    public function setCornerDotStyle($style, $color = null)
    {
        $this->cornerDotStyle = $style;
        if ($color !== null) {
            $this->cornerDotColor = $color;
        }
        return $this;
    }

    public function setSize($width, $height = null)
    {
        $this->width = $width;
        $this->height = $height ?: $width;
        $this->qrCode->setSize($width);
        return $this;
    }

    public function setMargin($margin)
    {
        $this->margin = $margin;
        $this->qrCode->setMargin($margin);
        return $this;
    }

    public function setFrameStyle($style)
    {
        $this->frameStyle = $style;
        return $this;
    }

    public function generate()
    {
        // Generate basic QR code
        $writer = new PngWriter();

        // Add logo if provided
        $logo = null;
        if ($this->logoPath) {
            $logo = Logo::create($this->logoPath)
                ->setResizeToWidth($this->logoWidth)
                ->setResizeToHeight($this->logoHeight);
        }

        // Generate the basic QR code
        $result = $writer->write($this->qrCode, $logo);

        // Get data URI
        $dataUri = $result->getDataUri();

        // Load image into Imagick for advanced styling
        $imageData = file_get_contents($dataUri);
        $imagick = new Imagick();
        $imagick->readImageBlob($imageData);

        // Apply advanced styling
        $this->applyAdvancedStyling($imagick);

        return $imagick;
    }

    public function getDataUri()
    {
        $imagick = $this->generate();
        $imagick->setImageFormat('png');
        return 'data:image/png;base64,' . base64_encode($imagick->getImageBlob());
    }

    public function saveToFile($path)
    {
        $imagick = $this->generate();
        $imagick->writeImage($path);
        return $this;
    }

    public function getImageBlob()
    {
        $imagick = $this->generate();
        return $imagick->getImageBlob();
    }

    protected function applyAdvancedStyling($imagick)
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        // Create a new canvas
        $canvas = new Imagick();
        $canvas->newImage($width, $height, new ImagickPixel('white'), 'png');

        // Apply background
        if ($this->gradientType) {
            $this->applyGradientBackground($canvas);
        } else {
            $bgColor = sprintf('rgb(%d,%d,%d)', $this->backgroundColor[0], $this->backgroundColor[1], $this->backgroundColor[2]);
            $canvas->floodFillPaintImage(new ImagickPixel($bgColor), 100, new ImagickPixel('white'), 0, 0, false);
        }

        // Apply frame if needed
        if ($this->frameStyle) {
            $this->applyFrame($canvas);
        }

        // Extract QR matrix for custom styling
        $qrMatrix = $this->extractQRMatrix($imagick);

        // Apply custom styling to QR code dots
        $this->applyCustomDotsStyle($canvas, $qrMatrix);

        // Return the styled QR code
        $imagick->clear();
        $imagick->readImageBlob($canvas->getImageBlob());
        $canvas->clear();

        return $imagick;
    }

    protected function extractQRMatrix($imagick)
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        // Calculate cell size
        $actualSize = $width - (2 * $this->margin);
        $moduleCount = 0;

        // Start at the top left corner of the QR code (after margin)
        $startX = $this->margin;
        $startY = $this->margin;

        // Find module count by reading pixels
        $pixelColor = $imagick->getImagePixelColor($startX, $startY);
        $lastColor = $pixelColor->getColorAsString();

        for ($x = $startX; $x < $width - $this->margin; $x++) {
            $pixelColor = $imagick->getImagePixelColor($x, $startY);
            $currentColor = $pixelColor->getColorAsString();

            if ($currentColor != $lastColor) {
                $moduleCount++;
                $lastColor = $currentColor;
            }
        }

        if ($moduleCount % 2 == 0) {
            $moduleCount = $moduleCount / 2;
        } else {
            $moduleCount = ($moduleCount + 1) / 2;
        }

        // Calculate module size
        $moduleSize = $actualSize / $moduleCount;

        // Read the QR matrix
        $matrix = [];
        for ($y = 0; $y < $moduleCount; $y++) {
            $row = [];
            for ($x = 0; $x < $moduleCount; $x++) {
                $pixelX = $startX + ($x * $moduleSize) + ($moduleSize / 2);
                $pixelY = $startY + ($y * $moduleSize) + ($moduleSize / 2);

                $pixelColor = $imagick->getImagePixelColor($pixelX, $pixelY);
                $colorValues = $pixelColor->getColor();

                // Check if it's a dark module
                $isDark = ($colorValues['r'] + $colorValues['g'] + $colorValues['b']) / 3 < 128;

                // Determine module type
                $type = 'data';

                // Check if it's a position detection pattern (finder pattern)
                if (($x < 7 && $y < 7) || // top-left
                    ($x < 7 && $y >= $moduleCount - 7) || // bottom-left
                    ($x >= $moduleCount - 7 && $y < 7)) { // top-right
                    $type = 'finder';
                }

                // Check if it's an alignment pattern
                if ($moduleCount > 21) { // Only larger QR codes have alignment patterns
                    if ($x >= $moduleCount - 9 && $x < $moduleCount - 4 &&
                        $y >= $moduleCount - 9 && $y < $moduleCount - 4) {
                        $type = 'alignment';
                    }
                }

                $row[] = [
                    'isDark' => $isDark,
                    'type' => $type,
                    'x' => $startX + ($x * $moduleSize),
                    'y' => $startY + ($y * $moduleSize),
                    'size' => $moduleSize
                ];
            }
            $matrix[] = $row;
        }

        return [
            'modules' => $matrix,
            'moduleCount' => $moduleCount,
            'moduleSize' => $moduleSize
        ];
    }

    protected function applyCustomDotsStyle($canvas, $qrMatrix)
    {
        $modules = $qrMatrix['modules'];
        $draw = new ImagickDraw();

        // Get main dot color
        if ($this->gradientType) {
            $dotColor = sprintf('rgb(%d,%d,%d)', $this->dotColor[0], $this->dotColor[1], $this->dotColor[2]);
            $draw->setFillColor(new ImagickPixel($dotColor));
        } else {
            $dotColor = sprintf('rgb(%d,%d,%d)', $this->dotColor[0], $this->dotColor[1], $this->dotColor[2]);
            $draw->setFillColor(new ImagickPixel($dotColor));
        }

        foreach ($modules as $row) {
            foreach ($row as $module) {
                if ($module['isDark']) {
                    $x = $module['x'];
                    $y = $module['y'];
                    $size = $module['size'];
                    $type = $module['type'];

                    // Set color based on module type
                    if ($type == 'finder' && $this->cornerSquareColor) {
                        $color = sprintf('rgb(%d,%d,%d)', $this->cornerSquareColor[0], $this->cornerSquareColor[1], $this->cornerSquareColor[2]);
                        $draw->setFillColor(new ImagickPixel($color));
                    } elseif ($type == 'alignment' && $this->cornerDotColor) {
                        $color = sprintf('rgb(%d,%d,%d)', $this->cornerDotColor[0], $this->cornerDotColor[1], $this->cornerDotColor[2]);
                        $draw->setFillColor(new ImagickPixel($color));
                    } else {
                        $dotColor = sprintf('rgb(%d,%d,%d)', $this->dotColor[0], $this->dotColor[1], $this->dotColor[2]);
                        $draw->setFillColor(new ImagickPixel($dotColor));
                    }

                    // Set shape based on module type
                    $shape = $this->dotShape;
                    if ($type == 'finder') {
                        $shape = $this->cornerSquareStyle;
                    } elseif ($type == 'alignment') {
                        $shape = $this->cornerDotStyle;
                    }

                    // Draw the appropriate shape
                    switch ($shape) {
                        case 'dots':
                            // Draw circle
                            $draw->circle(
                                $x + ($size / 2),
                                $y + ($size / 2),
                                $x + $size,
                                $y + ($size / 2)
                            );
                            break;
                        case 'rounded':
                            // Draw rounded rectangle
                            $cornerRadius = $size / 4;
                            $draw->roundRectangle(
                                $x, $y,
                                $x + $size, $y + $size,
                                $cornerRadius, $cornerRadius
                            );
                            break;
                        case 'square':
                        default:
                            // Draw rectangle
                            $draw->rectangle(
                                $x, $y,
                                $x + $size, $y + $size
                            );
                            break;
                    }
                }
            }
        }

        // Apply drawing
        $canvas->drawImage($draw);

        // Add logo if needed
        if ($this->logoPath && file_exists($this->logoPath)) {
            $logo = new Imagick($this->logoPath);
            $logo->resizeImage($this->logoWidth, $this->logoHeight, Imagick::FILTER_LANCZOS, 1);

            // Calculate logo position (center)
            $x = ($canvas->getImageWidth() - $logo->getImageWidth()) / 2;
            $y = ($canvas->getImageHeight() - $logo->getImageHeight()) / 2;

            // Composite logo
            $canvas->compositeImage($logo, Imagick::COMPOSITE_OVER, $x, $y);
            $logo->clear();
        }

        return $canvas;
    }

    protected function applyGradientBackground($canvas)
    {
        $width = $canvas->getImageWidth();
        $height = $canvas->getImageHeight();

        // Create a gradient
        $gradient = new Imagick();
        $gradient->newPseudoImage($width, $height, 'gradient:');

        // Apply the gradient based on type
        if ($this->gradientType == 'linear') {
            $draw = new ImagickDraw();
            $draw->setFillColor('black');
            $draw->rectangle(0, 0, $width, $height);

            $startColor = new ImagickPixel(sprintf('rgb(%d,%d,%d)',
                $this->gradientColors[0][0],
                $this->gradientColors[0][1],
                $this->gradientColors[0][2]
            ));

            $endColor = new ImagickPixel(sprintf('rgb(%d,%d,%d)',
                $this->gradientColors[1][0],
                $this->gradientColors[1][1],
                $this->gradientColors[1][2]
            ));

            $gradient->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_EDGE);
            $gradient->setImageGradient($startColor, $endColor);

        } elseif ($this->gradientType == 'radial') {
            $draw = new ImagickDraw();
            $draw->setFillColor('black');
            $draw->rectangle(0, 0, $width, $height);

            $startColor = new ImagickPixel(sprintf('rgb(%d,%d,%d)',
                $this->gradientColors[0][0],
                $this->gradientColors[0][1],
                $this->gradientColors[0][2]
            ));

            $endColor = new ImagickPixel(sprintf('rgb(%d,%d,%d)',
                $this->gradientColors[1][0],
                $this->gradientColors[1][1],
                $this->gradientColors[1][2]
            ));

            $gradient->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_EDGE);
            $gradient->radialGradientImage($startColor, $endColor, $width/2, $height/2, 0, $width/2);
        }

        // Apply gradient to canvas
        $canvas->compositeImage($gradient, Imagick::COMPOSITE_COPY, 0, 0);
        $gradient->clear();

        return $canvas;
    }

    protected function applyFrame($canvas)
    {
        $width = $canvas->getImageWidth();
        $height = $canvas->getImageHeight();
        $draw = new ImagickDraw();

        $frameColor = $this->dotColor;
        $color = sprintf('rgb(%d,%d,%d)', $frameColor[0], $frameColor[1], $frameColor[2]);
        $draw->setStrokeColor(new ImagickPixel($color));
        $draw->setStrokeWidth(4);
        $draw->setFillOpacity(0);

        if ($this->frameStyle == 'standard') {
            $draw->rectangle(
                $this->margin - 5,
                $this->margin - 5,
                $width - $this->margin + 5,
                $height - $this->margin + 5
            );
        } elseif ($this->frameStyle == 'circle') {
            $draw->circle(
                $width / 2,
                $height / 2,
                $width / 2,
                $this->margin
            );
        }

        $canvas->drawImage($draw);
        return $canvas;
    }
}
