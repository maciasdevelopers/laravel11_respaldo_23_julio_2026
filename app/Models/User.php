<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable{
	use HasApiTokens, HasFactory, Notifiable;
	protected $table = 'teci_usuarios_catalogo';
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		//'id',
		'user_token',
		'codigo_acceso',
		'email',
		'password',
		'token_usuario',
		'tipo',
		'registro',
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var array<int, string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'email_verified_at' => 'datetime',
	];

	public function notifications(){
		return $this->morphMany(NotificacionesModelo::class, 'notifiable')->orderBy('created_at', 'desc');
	}
}
