<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meetup extends Model
{
    protected $table = 'meetups';

    protected $fillable = ['name', 'closed'];

    protected $orderDirection = 'desc';

    protected $orderBy = 'id';

}
