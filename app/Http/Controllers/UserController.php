<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public const WRONG_PASS = 'Wrong password or username';
    public const USER_DONT_EXIST = 'User don\'t exist';

    /**
     * @throws Exception
     */
    public function login(Request $request, string $identifier): JsonResponse
    {
        $this->validate($request, [
            'password' => 'required'
        ]);
        if (!self::checkUsernameOrEmailExists($identifier)) {
            return response()->json(self::WRONG_PASS, 401);
        }
        $userPassword = User::select('password')->where('username', '=', $identifier)->get();
        if (!password_verify($request->input('password'), $userPassword->jsonSerialize()[0]['password'])) {
            return response()->json(self::WRONG_PASS, 401);
        }
        $apiKey = base64_encode(Str::random(40));
        $user = User::where('username', '=', $identifier)
            ->orWhere('email', "=", $identifier)->first();
        $user->api_key = $apiKey;
        $user->save();
        return response()->json(['status' => 'success', 'api_key' => $apiKey]);
    }

    public function registerUser(Request $request): JsonResponse
    {
        $this->validate($request, [
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'
        ]);
        if (self::checkUsernameOrEmailExists($request->input('email'))) {
            return response()->json('Email is taken', 401);
        }
        if (self::checkUsernameOrEmailExists($request->input('username'))) {
            return response()->json('Username is taken', 401);
        }
        $options = [
            'cost' => 11
        ];

        $passwordEncrypted = password_hash($request->input('password'), PASSWORD_BCRYPT, $options);

        $user = new User;
        $user->username = $request->input('username');
        $user->email = $request->input('email');
        $user->password = $passwordEncrypted;
        $user->save();

        return response()->json($user);
    }

    public function updateUser(Request $request, $identifier): JsonResponse
    {
        $this->validate($request, [
            'username' => 'unique:users',
            'email' => 'email|unique:users',
            'oldPassword' => 'required',
            'newPassword' => 'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'
        ]);
        if (!self::checkUsernameOrEmailExists($identifier)) {
            return response()->json(self::USER_DONT_EXIST, 401);
        }
        $oldPassword = User::select('password')->where('username', '=', $identifier)->get();
        if (!password_verify($request->input('oldPassword'), $oldPassword->jsonSerialize()[0]['password'])) {
            return response()->json('Wrong password or username', 401);
        }

        $user = User::where('username', '=', $identifier)
            ->orWhere('email', "=", $identifier)->first();
        if ($request->has('username')) {
            $user->username = $request->input('username');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email');
        }
        if ($request->has('newPassword')) {
            $user->password = $request->input('newPassword');
        }
        $user->save();

        return response()->json($user->username . '\\nUser successfully updated');
    }

    public function deleteUser($identifier): JsonResponse
    {
        if (!self::checkUsernameOrEmailExists($identifier)) {
            return response()->json(self::USER_DONT_EXIST, 404);
        }
        $user = User::where('username', '=', $identifier)
            ->orWhere('email', "=", $identifier)->first();
        $user->delete();

        return response()->json('Removed successfully.');
    }

    public function index(): JsonResponse
    {
        $users = User::select(['id', 'username', 'email'])->get();

        return response()->json($users);

    }

    public function getUser(string $identifier): JsonResponse
    {
        if (!self::checkUsernameOrEmailExists($identifier)) {
            return response()->json(self::USER_DONT_EXIST, 404);
        }
        $user = User::select(['id', 'username', 'email'])->where('username', '=', $identifier)
            ->orWhere('email', "=", $identifier)->get();

        return response()->json($user);
    }

    public static function checkUsernameOrEmailExists(string $identifier): bool
    {
        $userExist = User::where('username', '=', $identifier)
            ->orWhere('email', "=", $identifier)->exists();
        if ($userExist) {
            return true;
        }
        return false;
    }
}
