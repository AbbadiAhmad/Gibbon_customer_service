<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Service;

use Gibbon\FileUploader;
use Gibbon\Module\administration\Domain\AttachmentGateway;

/**
 * Reads uploaded files from a request (input name `attachments[]`) and stores them with Gibbon's FileUploader.
 */
class Attachments
{
    const FIELD = 'attachments';
    const MAX_FILES = 5;

    /** Normalise the $_FILES layout of a multiple upload into a list of single-file arrays. */
    public static function filesFromRequest(string $field = self::FIELD): array
    {
        $input = $_FILES[$field] ?? null;
        if (empty($input) || !is_array($input['name'] ?? null)) {
            return [];
        }

        $files = [];
        foreach ($input['name'] as $i => $name) {
            if ($name === '' || ($input['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = ['name' => $name, 'type' => $input['type'][$i], 'tmp_name' => $input['tmp_name'][$i], 'error' => $input['error'][$i], 'size' => $input['size'][$i]];
        }

        return array_slice($files, 0, self::MAX_FILES);
    }

    /**
     * @return int number of files that could not be stored
     */
    public static function save(AttachmentGateway $gateway, FileUploader $uploader, $csTicketID, $csTicketActivityID, $gibbonPersonID): int
    {
        $failed = 0;
        foreach (self::filesFromRequest() as $file) {
            $path = $uploader->uploadFromPost($file);
            if (empty($path)) {
                $failed++;
                continue;
            }
            $gateway->insert([
                'csTicketID'         => $csTicketID,
                'csTicketActivityID' => $csTicketActivityID ?: null,
                'gibbonPersonID'     => $gibbonPersonID,
                'fileName'           => mb_substr(basename($file['name']), 0, 255),
                'filePath'           => $path,
            ]);
        }

        return $failed;
    }

    /** Only paths below uploads/ without traversal may be served. */
    public static function isSafePath(string $path): bool
    {
        $path = ltrim($path, '/');
        return strpos($path, 'uploads/') === 0 && strpos($path, '..') === false;
    }
}
