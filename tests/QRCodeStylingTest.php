<?php

use App\Providers\QRCodeStyling;
use Orchestra\Testbench\TestCase;
use App\Providers\QRCodeStylingServiceProvider;

class QRCodeStylingTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [QRCodeStylingServiceProvider::class];
    }

    /** @test */
    public function it_can_generate_basic_qr_code()
    {
        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_customize_dot_style()
    {
        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $qr->setDotStyle('dots', [255, 0, 0]);
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_set_options_in_constructor()
    {
        $qr = new QRCodeStyling([
            'dotShape' => 'rounded',
            'dotColor' => [0, 0, 255],
            'backgroundColor' => [255, 255, 0],
        ]);
        $qr->setData('https://example.com');
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_generate_gradient_qr_code()
    {
        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $qr->setGradient('linear', [
            [255, 0, 0],
            [0, 0, 255],
        ]);
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_save_qr_code_to_file()
    {
        $tempFile = sys_get_temp_dir() . '/qr_test_' . uniqid() . '.png';

        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $qr->saveToFile($tempFile);

        $this->assertFileExists($tempFile);
        $this->assertGreaterThan(0, filesize($tempFile));

        // Clean up
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    /** @test */
    public function it_respects_size_settings()
    {
        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $qr->setSize(400, 400);

        $imagick = $qr->generate();

        $this->assertEquals(400, $imagick->getImageWidth());
        $this->assertEquals(400, $imagick->getImageHeight());
    }

    /** @test */
    public function it_can_use_different_corner_styles()
    {
        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $qr->setCornerSquareStyle('dots');
        $qr->setCornerDotStyle('rounded');
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_add_frame()
    {
        $qr = new QRCodeStyling();
        $qr->setData('https://example.com');
        $qr->setFrameStyle('standard');
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_be_resolved_from_container()
    {
        $qr = app('qr-styling');
        $this->assertInstanceOf(QRCodeStyling::class, $qr);

        $qr->setData('https://example.com');
        $dataUri = $qr->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_applies_custom_settings_from_config()
    {
        // Set custom config
        config(['qr-styling.dot_shape' => 'dots']);
        config(['qr-styling.dot_color' => [255, 0, 0]]);

        // Create QR code using config values
        $qr = new QRCodeStyling(config('qr-styling'));
        $qr->setData('https://example.com');

        // Just check it generates without errors
        $dataUri = $qr->getDataUri();
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /** @test */
    public function it_can_handle_method_chaining()
    {
        $dataUri = (new QRCodeStyling())
            ->setData('https://example.com')
            ->setDotStyle('rounded')
            ->setBackgroundColor([240, 240, 240])
            ->setMargin(20)
            ->setSize(350)
            ->getDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }
}
