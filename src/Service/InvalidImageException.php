<?php

namespace App\Service;

/**
 * Thrown by ImageUploader when a file is rejected. The message is safe to show to the user.
 */
final class InvalidImageException extends \RuntimeException
{
}
