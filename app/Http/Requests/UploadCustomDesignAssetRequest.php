<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\CustomDesignService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Upload contract for customiser bitmaps. JPG/JPEG/PNG only, stored privately.
 *
 * `file` is the canonical field; `image`, `asset` and `photo` are accepted as aliases so
 * the front-end may use any of them. The draft comes from the route parameter or `draft_id`.
 */
class UploadCustomDesignAssetRequest extends FormRequest
{
    public const FILE_FIELDS = ['file', 'image', 'asset', 'photo'];

    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Customer) === true
            && $this->user()->customer !== null;
    }

    public function rules(): array
    {
        $file = [
            'required_without_all:'.implode(',', self::FILE_FIELDS),
            'file',
            'mimes:jpg,jpeg,png',
            'mimetypes:image/jpeg,image/png',
            'max:'.$this->maxKilobytes(),
        ];

        $rules = [
            'draft_id' => ['sometimes', 'nullable', 'uuid', 'exists:custom_design_drafts,id'],
        ];

        foreach (self::FILE_FIELDS as $field) {
            $rules[$field] = $file;
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'draft_id.exists' => 'Draft desain tidak ditemukan.',
        ];

        foreach (self::FILE_FIELDS as $field) {
            $messages[$field.'.required_without_all'] = 'Gambar wajib diunggah.';
            $messages[$field.'.mimes'] = 'Format gambar harus JPG atau PNG.';
            $messages[$field.'.mimetypes'] = 'Format gambar harus JPG atau PNG.';
            $messages[$field.'.max'] = 'Ukuran gambar maksimal '.$this->maxKilobytes().' KB.';
        }

        return $messages;
    }

    public function maxKilobytes(): int
    {
        return app(CustomDesignService::class)->maxAssetKilobytes();
    }

    /** First uploaded file across the accepted field aliases. */
    public function uploadedFile(): ?UploadedFile
    {
        foreach (self::FILE_FIELDS as $field) {
            $file = $this->file($field);

            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    public function draftKey(): ?string
    {
        $key = $this->input('draft_id');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
