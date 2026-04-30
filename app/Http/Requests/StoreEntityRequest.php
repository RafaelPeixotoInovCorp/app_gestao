<?php

namespace App\Http\Requests;

use App\Models\Entity;
use Illuminate\Foundation\Http\FormRequest;

class StoreEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'is_client' => ['required', 'boolean'],
            'is_supplier' => ['required', 'boolean'],
            'nif' => ['required', 'string', 'regex:/^\d{9}$/'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{4}-\d{3}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'gdpr_consent' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $postal = $this->input('postal_code');
        $website = $this->input('website');

        $this->merge([
            'is_client' => $this->boolean('is_client'),
            'is_supplier' => $this->boolean('is_supplier'),
            'active' => $this->boolean('active'),
            'gdpr_consent' => $this->has('gdpr_consent') ? $this->boolean('gdpr_consent') : null,
            'postal_code' => $postal === '' ? null : $postal,
            'website' => $website === '' ? null : $website,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('is_client') && ! $this->boolean('is_supplier')) {
                $validator->errors()->add('is_client', 'Seleccione Cliente e/ou Fornecedor.');
            }

            if ($this->filled('nif') && preg_match('/^\d{9}$/', (string) $this->input('nif'))) {
                $hash = Entity::hashNif($this->input('nif'));
                if (Entity::query()->where('nif_hash', $hash)->whereNull('deleted_at')->exists()) {
                    $validator->errors()->add('nif', 'Este NIF já está registado.');
                }
            }

            $user = $this->user();
            if ($this->boolean('is_client') && ! $user->hasRole('admin') && ! $user->can('module.clients.create')) {
                $validator->errors()->add('is_client', 'Sem permissão para criar clientes.');
            }
            if ($this->boolean('is_supplier') && ! $user->hasRole('admin') && ! $user->can('module.suppliers.create')) {
                $validator->errors()->add('is_supplier', 'Sem permissão para criar fornecedores.');
            }
        });
    }
}
