<?php

return [
    'required'  => 'Le champ :attribute est obligatoire.',
    'email'     => 'Le champ :attribute doit être une adresse e-mail valide.',
    'unique'    => 'Cet :attribute est déjà utilisé.',
    'min'       => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'max'      => [
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'confirmed' => 'La confirmation du :attribute ne correspond pas.',
    'string'    => 'Le champ :attribute doit être une chaîne de caractères.',

    'attributes' => [
        'name'                  => 'nom',
        'email'                 => 'e-mail',
        'password'              => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'content'               => 'contenu',
        'text'                  => 'texte',
    ],
];
