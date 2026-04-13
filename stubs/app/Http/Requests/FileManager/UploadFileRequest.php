<?php

namespace App\Http\Requests\FileManager;

use App\Models\Setting;

class UploadFileRequest extends FileManagerRequest
{
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
        $raw = Setting::getValue('file_manager.accepted_mimes', '[]');

        if (is_array($raw)) {
            $mimes = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            $mimes = is_array($decoded) ? $decoded : [];
        }

        if ((bool) Setting::getValue('file_manager.allow_video', false)) {
            $mimes = [...$mimes, 'video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];
        }

        if ((bool) Setting::getValue('file_manager.allow_audio', false)) {
            $mimes = [...$mimes, 'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'];
        }

        return array_values(array_unique($mimes));
    }
}
