<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Cache;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function login_old(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'projects_id' => 'required',
        ]);

        // Retrieve the user by email
        $user = \App\Models\User::where('email', $request->input('email'))->first();

        if ($user) {
            // Check if the user is a super admin
            if ($user->hasRole('superadmin') || $user->project_id == $request->input('projects_id')) {
                // Proceed with authentication
                $credentials = $request->only('email', 'password');
                if (Auth::attempt($credentials, true)) {
                    Cache::put('selected_action', $request->input('projects_id')); // Store the selected action in cache
                    return to_route('dashboard');
                } else {
                    throw ValidationException::withMessages([
                        'password' => [trans('auth.failed')],
                    ]);
                }
            } else {
                // Throw an error if the project ID doesn't match and the user is not a super admin
                throw ValidationException::withMessages([
                    'projects_id' => ['The selected project does not match our records.'],
                ]);
            }
        } else {
            // Throw an error if the user doesn't exist
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }
    }
    
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'projects_id' => 'required',
        ]);

        // Retrieve the user by email
        $user = \App\Models\User::where('email', $request->input('email'))->first();

        if ($user) {
            // Check if the user is a super admin or project ID matches
            if ($user->hasRole('superadmin') || $user->project_id == $request->input('projects_id')) {
                // Proceed with authentication
                $credentials = $request->only('email', 'password');
                if (Auth::attempt($credentials, true)) {
                    // // Create a cookie with the 'selected_action' key and set it for 30 days (43,200 minutes)
                    $cookie = cookie('selected_action', $request->input('projects_id'), 43200); // Cookie valid for 30 days

                    return to_route('dashboard')->withCookie($cookie);
                } else {
                    throw ValidationException::withMessages([
                        'password' => [trans('auth.failed')],
                    ]);
                }
            } else {
                // Throw an error if the project ID doesn't match and the user is not a super admin
                throw ValidationException::withMessages([
                    'projects_id' => ['The selected project does not match our records.'],
                ]);
            }
        } else {
            // Throw an error if the user doesn't exist
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }
    }




    protected function authenticated(Request $request, $user)
    {
        Alert::info('Selamat datang ' . $user->name)->toToast();
        return to_route('dashboard');
    }
}
