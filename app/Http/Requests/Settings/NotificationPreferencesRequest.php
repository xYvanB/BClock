<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enum\NotificationKind;
use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (NotificationKind::values() as $kind) {
            $rules[$kind] = ['required', 'array:app,mail'];
            $rules["{$kind}.app"] = ['required', 'boolean'];
            $rules["{$kind}.mail"] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * Le richieste da approvare restano sempre attive nell'app, qualunque cosa arrivi dal browser.
     *
     * @return array<string, array{app: bool, mail: bool}>
     */
    public function preferences(): array
    {
        $preferences = [];

        foreach (NotificationKind::cases() as $kind) {
            $preferences[$kind->value] = [
                'app' => $kind->isMandatoryInApp() || $this->boolean("{$kind->value}.app"),
                'mail' => $this->boolean("{$kind->value}.mail"),
            ];
        }

        return $preferences;
    }
}
