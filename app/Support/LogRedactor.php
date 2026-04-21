<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class LogRedactor
{
    /**
     * Recursively redact values whose keys are in the deny list (case-insensitive).
     *
     * @param  list<string>  $keys
     */
    public static function redactBody(mixed $body, array $keys): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

        $lowerKeys = array_map('strtolower', $keys);

        $result = [];
        foreach ($body as $key => $value) {
            if (in_array(strtolower((string) $key), $lowerKeys, true)) {
                $result[$key] = '[REDACTED]';
            } else {
                $result[$key] = self::redactBody($value, $keys);
            }
        }

        return $result;
    }

    /**
     * Lowercase header names and remove any in the deny list, or mark others redacted.
     *
     * @param  array<string, mixed>  $headers
     * @param  list<string>  $deny
     * @return array<string, mixed>
     */
    public static function redactHeaders(array $headers, array $deny): array
    {
        $lowerDeny = array_map('strtolower', $deny);

        $result = [];
        foreach ($headers as $name => $value) {
            $lowerName = strtolower((string) $name);
            if (in_array($lowerName, $lowerDeny, true)) {
                // Skip entirely (drop the header)
                continue;
            }
            $result[$lowerName] = $value;
        }

        return $result;
    }

    /**
     * Summarize a multipart request: text fields (redacted) + file metadata.
     *
     * @param  list<string>  $redactKeys
     * @return array<string, mixed>
     */
    public static function summarizeMultipartBody(Request $request, array $redactKeys): array
    {
        $files = $request->files->all();
        $fileKeys = array_keys($files);

        // Text fields only (exclude file keys)
        $textFields = array_diff_key($request->all(), array_flip($fileKeys));
        $redacted = self::redactBody($textFields, $redactKeys);

        // File metadata
        foreach ($files as $field => $file) {
            if ($file instanceof UploadedFile) {
                $redacted[$field] = [
                    'filename' => $file->getClientOriginalName(),
                    'size_bytes' => $file->getSize(),
                    'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                ];
            } elseif (is_array($file)) {
                // Multiple files under same field name
                $redacted[$field] = array_map(function (mixed $f): mixed {
                    if ($f instanceof UploadedFile) {
                        return [
                            'filename' => $f->getClientOriginalName(),
                            'size_bytes' => $f->getSize(),
                            'mime_type' => $f->getMimeType() ?? $f->getClientMimeType(),
                        ];
                    }

                    return $f;
                }, $file);
            }
        }

        return $redacted;
    }
}
