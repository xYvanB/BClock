<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\AcceptInvitationData;
use App\Enum\AppLocale;
use App\Enum\TimeFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    /**
     * Access is guaranteed by the signed link (signed middleware).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['required', Rule::enum(AppLocale::class)],
            'time_format' => ['required', Rule::enum(TimeFormat::class)],
        ];
    }

    public function toDto(): AcceptInvitationData
    {
        return new AcceptInvitationData(
            password: $this->string('password')->toString(),
            locale: AppLocale::from($this->string('locale')->toString()),
            timeFormat: TimeFormat::from($this->string('time_format')->toString()),
        );
    }
}
