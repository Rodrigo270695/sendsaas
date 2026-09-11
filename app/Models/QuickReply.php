<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuickReplyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $title
 * @property ?string $shortcut
 * @property string $body
 * @property ?string $created_by_id
 */
class QuickReply extends Model
{
    /** @use HasFactory<QuickReplyFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'quick_replies';

    protected $fillable = [
        'title',
        'shortcut',
        'body',
        'created_by_id',
    ];
}
