<?php

namespace Database\Factories;

use App\Models\QuickReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickReply>
 */
class QuickReplyFactory extends Factory
{
    protected $model = QuickReply::class;

    public function definition(): array
    {
        return [
            'title' => 'Saludo',
            'shortcut' => 'SALUDO',
            'body' => 'Hola {{nombre}}, gracias por escribirnos. ¿En qué podemos ayudarte?',
        ];
    }
}
