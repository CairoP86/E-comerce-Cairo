<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'Ingresa un correo electrónico válido.',
    'unique' => 'Este :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'string' => 'El campo :attribute debe ser texto.',
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'password' => ['letters' => 'La contraseña debe incluir letras.', 'numbers' => 'La contraseña debe incluir números.'],
    'attributes' => ['name' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña', 'password_confirmation' => 'confirmación de contraseña'],
];
