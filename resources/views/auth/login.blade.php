<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ ENV('APP_NAME') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" />
    <!-- Google Font: Inter (modern alternative to Source Sans Pro) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('template/admin/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    {!! htmlScriptTagJsApi() !!}
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .property-bg {
            background-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-50">
    @php
        if (!$errors->isEmpty()) {
            alert()->error('Pemberitahuan', implode('<br>', $errors->all()))->toToast()->toHtml();
        }
    @endphp

    <div class="min-h-screen flex">
        <!-- Left side with property image -->
        <div class="hidden lg:block w-1/2 property-bg relative">
            <div class="absolute inset-0 bg-blue-900 opacity-50"></div>
            <div class="relative z-10 h-full flex flex-col justify-between p-12 text-white">
                <div>
                    <h1 class="text-3xl font-bold">Blue Marketing</h1>
                    <p class="mt-2 text-blue-100">Property Management System</p>
                </div>
                <div class="mb-8">
                    <h2 class="text-2xl font-semibold">Streamline Your Property Operations</h2>
                    <p class="mt-4 text-blue-100">Manage properties, tenants, and finances all in one place.</p>
                </div>
            </div>
        </div>

        <!-- Right side with login form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="w-full max-w-md">
                <div class="text-center mb-8">
                    <!-- Replace with your logo -->
                    <div class="flex justify-center mb-4">
                        <div class="w-24 h-16 bg-blue-600 p-2 rounded-lg flex items-center justify-center text-white">
                            {{-- <i class="fas fa-building text-2xl"></i> --}}
                            <img src="{{ asset('images/logo/blue-marketing-logo.png') }}" alt="logo">
                        </div>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Welcome to {{ ENV('APP_NAME') }}</h1>
                    <p class="text-gray-600 mt-2">Sign in to your property management account</p>
                </div>

                <div class="bg-white rounded-xl shadow-md p-8">
                    <form method="POST" id="#recaptcha-form" action="{{ route('login') }}">
                        @csrf
                        <div class="space-y-5">
                            <!-- Email Field -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="far fa-envelope text-gray-400"></i>
                                    </div>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                                        class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 focus:outline-none h-12 @error('email') border-red-500 @enderror"
                                        placeholder="your@email.com">
                                </div>
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Password Field -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input id="password" type="password" name="password" required autocomplete="current-password"
                                        class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 focus:outline-none h-12 @error('password') border-red-500 @enderror"
                                        placeholder="••••••••">
                                </div>
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Project Selection -->
                            <div>
                                <label for="projects_id" class="block text-sm font-medium text-gray-700 mb-1">Select Property</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-home text-gray-400"></i>
                                    </div>
                                    <select name="projects_id" id="projects_id"
                                        class="form-select w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 focus:outline-none h-12 appearance-none bg-white @error('projects_id') border-red-500 @enderror">
                                        <option value="">Select a property</option>
                                        @foreach (getProjects() as $v)
                                            <option value="{{ $v->id }}">{{ $v->project }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('projects_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Remember Me (hidden as per original) -->
                            <div class="hidden">
                                <div class="flex items-center">
                                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                                        Remember me
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div>
                                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                                    Sign in
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Footer links -->
                <div class="mt-6 text-center text-sm text-gray-500">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="font-medium text-blue-600 hover:text-blue-500">
                            Forgot your password?
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('sweetalert::alert')
    <!-- jQuery -->
    <script src="{{ asset('template/admin/plugins/jquery/jquery.min.js') }}"></script>
</body>
</html>