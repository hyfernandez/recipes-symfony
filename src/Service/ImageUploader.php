<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates an uploaded image and moves it to the public uploads directory.
 *
 * The target directory is injected (see config/services.yaml) so tests can point it to a temp folder.
 */
final class ImageUploader
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const MAX_SIZE_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly string $uploadsDirectory)
    {
    }

    /**
     * @return string the stored filename (not the full path), ready for Recipe::setImageFilename()
     *
     * @throws InvalidImageException
     */
    public function upload(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            // e.g. the file exceeded upload_max_filesize in php.ini
            throw new InvalidImageException($file->getErrorMessage());
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new InvalidImageException(sprintf('The image is too large (max %d MB).', self::MAX_SIZE_BYTES / 1024 / 1024));
        }

        // guessExtension() inspects the file content (MIME type); the client-supplied
        // name can be anything, so it is never trusted to pick the extension.
        $extension = $file->guessExtension();
        if (null === $extension || !\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidImageException(sprintf('Only %s images are allowed.', implode(', ', self::ALLOWED_EXTENSIONS)));
        }

        // Random name: avoids collisions and never reuses the user-supplied filename.
        $filename = bin2hex(random_bytes(8)).'.'.$extension;
        $file->move($this->uploadsDirectory, $filename);

        return $filename;
    }

    public function delete(string $filename): void
    {
        // basename() blocks path traversal if a tampered value ever reaches this method.
        $path = $this->uploadsDirectory.'/'.basename($filename);

        if (is_file($path)) {
            unlink($path);
        }
    }
}
