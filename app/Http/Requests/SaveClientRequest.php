<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ClientContactData;
use App\DTO\ClientData;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveClientRequest extends FormRequest
{
    /**
     * Authorizes before validation (ClientPolicy), so whoever cannot gets 403 and not field errors.
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            ? $this->user()->can('update', $client)
            : $this->user()->can('create', Client::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Client|null $client */
        $client = $this->route('client');

        return [
            'company_name' => ['required', 'string', 'max:150'],
            'contacts' => ['present', 'array'],
            'contacts.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('client_contacts', 'id')->where('client_id', $client?->id ?? 0),
            ],
            'contacts.*.name' => ['required', 'string', 'max:120'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $primaries = collect($this->input('contacts', []))
                    ->filter(fn (mixed $contact): bool => is_array($contact) && ! empty($contact['is_primary']))
                    ->count();

                if ($primaries > 1) {
                    $validator->errors()->add('contacts', __('Può esserci un solo referente principale.'));
                }
            },
        ];
    }

    public function toDto(): ClientData
    {
        return new ClientData(
            companyName: $this->string('company_name')->trim()->toString(),
            contacts: array_values(array_map(
                fn (array $contact): ClientContactData => new ClientContactData(
                    id: isset($contact['id']) ? (int) $contact['id'] : null,
                    name: trim((string) $contact['name']),
                    email: filled($contact['email'] ?? null) ? trim((string) $contact['email']) : null,
                    phone: filled($contact['phone'] ?? null) ? trim((string) $contact['phone']) : null,
                    isPrimary: (bool) ($contact['is_primary'] ?? false),
                ),
                $this->input('contacts', []),
            )),
        );
    }
}
