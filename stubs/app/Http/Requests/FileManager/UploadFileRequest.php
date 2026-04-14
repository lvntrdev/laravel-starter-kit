<?php

namespace App\Http\Requests\FileManager;

use App\Models\Setting;

class UploadFileRequest extends FileManagerRequest
{
    /**
     * Baseline MIME list used when no settings are configured yet,
     * so the uploader never crashes with "mimetypes:" on a fresh install.
     *
     * @var array<int, string>
     */
    private const DEFAULT_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'text/csv',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSizeKb = (int) Setting::getValue('file_manager.max_size_kb', 10240);
        $mimetypes = $this->acceptedMimes();

        return [
            ...$this->contextRules(),
            'folder_id' => ['nullable', 'uuid'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                'file',
                "max:{$maxSizeKb}",
                'mimetypes:'.implode(',', $mimetypes),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function acceptedMimes(): array
    {
        $raw = Setting::getValue('file_manager.accepted_mimes', null);

        if (is_array($raw)) {
            $mimes = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $mimes = is_array($decoded) ? $decoded : [];
        } else {
            $mimes = [];
        }

        if ((bool) Setting::getValue('file_manager.allow_video', false)) {
            $mimes = [...$mimes, 'video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];
        }

        if ((bool) Setting::getValue('file_manager.allow_audio', false)) {
            $mimes = [...$mimes, 'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'];
        }

        if ($mimes === []) {
            $mimes = self::DEFAULT_MIMES;
        }

        return array_values(array_unique($mimes));
    }
}
