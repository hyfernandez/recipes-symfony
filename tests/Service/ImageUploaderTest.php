<?php

namespace App\Tests\Service;

use App\Service\ImageUploader;
use App\Service\InvalidImageException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Plain unit test: no kernel, no database. The uploads directory is a temp folder.
 */
final class ImageUploaderTest extends TestCase
{
    // 1x1 transparent PNG
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private string $dir;
    private ImageUploader $uploader;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/uploader_'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->uploader = new ImageUploader($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testStoresAValidImageWithARandomName(): void
    {
        $filename = $this->uploader->upload($this->uploadedFile(base64_decode(self::PNG), 'my holiday photo.png'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}\.png$/', $filename);
        self::assertFileExists($this->dir.'/'.$filename);
    }

    public function testTheExtensionComesFromTheContentNotFromTheClientName(): void
    {
        // A PNG uploaded as "photo.jpg" is stored as .png
        $filename = $this->uploader->upload($this->uploadedFile(base64_decode(self::PNG), 'photo.jpg'));

        self::assertStringEndsWith('.png', $filename);
    }

    public function testRejectsANonImageDisguisedAsAnImage(): void
    {
        $this->expectException(InvalidImageException::class);

        $this->uploader->upload($this->uploadedFile('<?php echo "hi";', 'evil.png'));
    }

    public function testRejectsAFileOverTwoMegabytes(): void
    {
        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('too large');

        $this->uploader->upload($this->uploadedFile(base64_decode(self::PNG).str_repeat('0', ImageUploader::MAX_SIZE_BYTES), 'big.png'));
    }

    public function testDeleteRemovesTheFile(): void
    {
        $filename = $this->uploader->upload($this->uploadedFile(base64_decode(self::PNG), 'a.png'));

        $this->uploader->delete($filename);

        self::assertFileDoesNotExist($this->dir.'/'.$filename);
    }

    public function testDeleteCannotEscapeTheUploadsDirectory(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'outside');

        $this->uploader->delete('../'.basename($outside));

        self::assertFileExists($outside);   // basename() kept the delete inside the uploads dir
        unlink($outside);
    }

    private function uploadedFile(string $content, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        // test: true lets the UploadedFile accept a file that was not really uploaded over HTTP
        return new UploadedFile($path, $clientName, null, null, true);
    }
}
