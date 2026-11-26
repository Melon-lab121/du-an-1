<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\User;
use App\Services\Auth;

final class AuthController extends Controller
{
    public function __construct(private Auth $auth, private Session $session)
    {
    }

    public function showLogin(): Response
    {
        return $this->view('auth/login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ], ['email' => 'Email', 'password' => 'Mật khẩu']);

        if (!$this->auth->attempt($data['email'], $data['password'])) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.'], ['email' => $data['email']]);
        }

        $this->flash('success', 'Xin chào ' . ($this->auth->user()['name'] ?? '') . '!');
        $intended = (string) $this->session->pull('url.intended', '');
        if ($intended !== '') {
            return $this->redirect($intended);
        }
        return $this->redirect($this->auth->isAdmin() ? '/admin' : '/');
    }

    public function showRegister(): Response
    {
        return $this->view('auth/register');
    }

    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|phone',
            'password' => 'required|string|min:8|max:72|confirmed',
        ], ['name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại', 'password' => 'Mật khẩu']);

        $id = User::register($data);
        $this->auth->login(User::findOrFail($id));
        $this->flash('success', 'Đăng ký thành công. Chào mừng bạn!');
        return $this->redirect('/');
    }

    public function logout(): Response
    {
        $this->auth->logout();
        $this->flash('success', 'Đã đăng xuất.');
        return $this->redirect('/');
    }
}
