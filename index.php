<?php
// Start session to check for any messages if needed, though mostly we use GET params here
if (!isset($_SESSION)) {
    session_start();
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>MHWUN Portal - Modern Login</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#1e40af", // Deep Medical Blue
                        "background-light": "#f8fafc",
                        "background-dark": "#020617",
                    },
                    fontFamily: {
                        display: ["Inter", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
                        'xl': '1rem',
                    },
                },
            },
        };

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .login-image-overlay {
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.85) 0%, rgba(30, 58, 138, 0.7) 100%);
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark transition-colors duration-300">
    <div class="flex flex-col md:flex-row min-h-screen">
        <div class="hidden md:flex md:w-1/2 relative overflow-hidden">
            <img alt="Healthcare professionals working together" class="absolute inset-0 w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCc4573c6zCImnmW1irzRZNuddeEIQErOjJchctVx9eQP3c1ciSiz4KbF0sEFNtHKWaR0FU9xDfuSMd4RFwzVBiQ__1RbiXjeT1pBgBQxV-TKoLH8K-rHiq75rf1Ze8r09i97VrIA-RNWbPcK6AB82t_khvOhPUwHOtzyeO2rypnrkK_gwpD0FjK4Sjq6E_HjeAYjw0_7jsic7ig07Y1QzHI7MDFfyVLWbS3rXuvyVZeknDdvglqRqSRygBTVSfeJdJK8nklMHamKHU"/>
            <div class="absolute inset-0 login-image-overlay flex flex-col justify-center px-12 lg:px-24 text-white">
                <div class="mb-8">
                    <div class="bg-white/10 w-20 h-20 rounded-2xl flex items-center justify-center backdrop-blur-md mb-6 border border-white/20">
                        <span class="material-icons-round text-4xl">medical_services</span>
                    </div>
                    <h1 class="text-4xl lg:text-5xl font-bold leading-tight mb-4">
                        Uniting Hands for a <br/><span class="text-blue-200">Healthier Nation.</span>
                    </h1>
                    <p class="text-lg text-blue-100 max-w-md">
                        The Medical and Health Workers' Union of Nigeria (MHWUN) Member Portal. Access your benefits, news, and professional resources.
                    </p>
                </div>
                <div class="flex items-center gap-4 text-sm font-medium text-blue-200">
                    <span class="flex items-center gap-1"><span class="material-icons-round text-sm">verified_user</span> Secure Access</span>
                    <span class="flex items-center gap-1"><span class="material-icons-round text-sm">group</span> 100k+ Members</span>
                </div>
            </div>
            <div class="absolute top-8 left-8 flex items-center gap-3">
                <div class="bg-white rounded-full p-2 shadow-lg">
                    <img alt="MHWUN Logo Small" class="w-8 h-8 rounded-full" src="image/mhwun_logo.png"/>
                </div>
                <span class="text-white font-bold tracking-wider text-xl">MHWUN</span>
            </div>
        </div>
        <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 lg:px-12 bg-white dark:bg-background-dark relative">
            <div class="absolute top-8 right-8 flex items-center gap-4">
                <button class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-slate-500 dark:text-slate-400" onclick="toggleDarkMode()">
                    <span class="material-icons-round dark:hidden">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400">light_mode</span>
                </button>
                <a class="text-sm font-medium text-slate-500 hover:text-primary dark:text-slate-400 transition-colors flex items-center gap-1" href="#">
                    <span class="material-icons-round text-sm">help_outline</span> Need help?
                </a>
            </div>
            
            <div class="w-full max-w-md">
                <div class="md:hidden flex flex-col items-center mb-10">
                    <div class="bg-primary p-3 rounded-2xl mb-4 shadow-xl shadow-primary/20">
                        <span class="material-icons-round text-3xl text-white">medical_services</span>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">MHWUN Portal</h2>
                </div>

                <!-- Error Alert Container -->
                <div id="error-alert" class="hidden mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-md shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <span class="material-icons-round text-red-500">error</span>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800" id="error-title">Login Failed</h3>
                            <div class="mt-1 text-sm text-red-700" id="error-message">
                                Invalid username or password.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-10 text-center md:text-left">
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">Welcome Back</h2>
                    <p class="text-slate-500 dark:text-slate-400">Please enter your credentials to access your account.</p>
                </div>
                
                <form action="login_auth.php" method="POST" class="space-y-6" id="loginForm">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="username">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="material-icons-round text-slate-400 text-xl">person_outline</span>
                            </div>
                            <input class="block w-full pl-11 pr-4 py-3 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 text-slate-900 dark:text-white rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-400" id="username" name="uname" placeholder="Enter your username" required="" type="text"/>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300" for="password">Password</label>
                            <!-- <a class="text-xs font-semibold text-primary hover:underline" href="#">Forgot password?</a> -->
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="material-icons-round text-slate-400 text-xl">lock_open</span>
                            </div>
                            <input class="block w-full pl-11 pr-12 py-3 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 text-slate-900 dark:text-white rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all placeholder:text-slate-400" id="password" name="passwd" placeholder="••••••••" required="" type="password"/>
                            <button class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none" type="button" id="togglePassword">
                                <span class="material-icons-round text-xl" id="toggleIcon">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input class="h-4 w-4 text-primary focus:ring-primary border-slate-300 dark:border-slate-700 rounded transition-colors bg-white dark:bg-slate-900" id="remember-me" name="remember-me" type="checkbox"/>
                        <label class="ml-2 block text-sm text-slate-600 dark:text-slate-400" for="remember-me">
                            Remember me for 30 days
                        </label>
                    </div>
                    <button class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-primary/20 transform transition-all active:scale-[0.98] flex items-center justify-center gap-2" type="submit">
                        Sign In
                        <span class="material-icons-round text-lg">arrow_forward</span>
                    </button>
                </form>
                
                <div class="mt-10 text-center space-y-4">
                    <p class="text-slate-600 dark:text-slate-400 text-sm">
                        Don't have an account yet? 
                        <a class="text-primary font-bold hover:underline" href="#">Apply for Membership</a>
                    </p>
                    <div class="pt-8 border-t border-slate-100 dark:border-slate-800 flex flex-wrap justify-center gap-x-6 gap-y-2 text-xs text-slate-400">
                        <a class="hover:text-slate-600 dark:hover:text-slate-300" href="#">Privacy Policy</a>
                        <a class="hover:text-slate-600 dark:hover:text-slate-300" href="#">Terms of Service</a>
                        <span>© <?php echo date("Y"); ?> MHWUN</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Password Visibility Toggle
            $('#togglePassword').on('click', function() {
                const passwordField = $('#password');
                const icon = $('#toggleIcon');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                icon.text(type === 'password' ? 'visibility' : 'visibility_off');
            });

            // Check URL parameters for errors
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            const expired = urlParams.get('Expired');

            if (error) {
                let title = 'Login Failed';
                let message = 'Invalid username or password.';
                
                if (error === 'system_error') {
                    title = 'System Error';
                    message = 'A system error occurred. Please try again later.';
                }

                $('#error-title').text(title);
                $('#error-message').text(message);
                $('#error-alert').removeClass('hidden').hide().fadeIn();
            } else if (expired !== null) {
                $('#error-title').text('License Expired');
                $('#error-message').text('Please Contact your Administrator.');
                $('#error-alert').removeClass('hidden').hide().fadeIn();
            }
        });
    </script>
</body>
</html>
