<?php

namespace App\Http\Requests;

use App\Support\CostaRicaTerritories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'exact_address', 'additional'] as $key) {
            if (is_string($this->input($key))) {
                $clean[$key] = trim($this->input($key));
            }
        }
        if (isset($clean['phone'])) {
            $phone = preg_replace('/[\s()-]/u', '', $clean['phone']);
            $clean['phone'] = preg_match('/^[2-8][0-9]{7}$/', $phone) ? '+506'.$phone : $phone;
        }
        $this->merge($clean);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'first_name' => ['required', 'string', 'max:100', 'regex:/\p{L}/u'],
            'last_name' => ['required', 'string', 'max:150', 'regex:/\p{L}/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'regex:/^\+506[2-8][0-9]{7}$/'],
            'province_code' => ['required', 'string', 'size:1'],
            'canton_code' => ['required', 'string', 'size:3'],
            'district_code' => ['required', 'string', 'size:5'],
            'exact_address' => ['required', 'string', 'min:10', 'max:1000'],
            'additional' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Completa este campo.', 'string' => 'Escribe un valor válido.', 'max' => 'El texto supera el máximo permitido.', 'min' => 'Incluye al menos :min caracteres.', 'size' => 'Selecciona una opción válida.', 'email.email' => 'Escribe un correo válido.', 'phone.regex' => 'Usa un teléfono de Costa Rica de 8 dígitos, con +506 opcional.', 'regex' => 'Escribe un nombre válido.', 'token.uuid' => 'Actualiza el checkout e inténtalo de nuevo.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (array_diff(array_keys($this->all()), [...array_keys($this->rules()), '_token', '_method'])) {
                $validator->errors()->add('checkout', 'La solicitud contiene campos no permitidos.');
            }
            if (! $validator->errors()->hasAny(['province_code', 'canton_code', 'district_code']) && ! CostaRicaTerritories::find($this->province_code, $this->canton_code, $this->district_code)) {
                $validator->errors()->add('district_code', 'El distrito no corresponde a la provincia y cantón seleccionados.');
            }
        }];
    }
}
