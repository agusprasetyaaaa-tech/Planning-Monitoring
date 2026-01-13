<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Page Not Found | Planly</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Styles -->
    @vite(['resources/js/app.js'])

    <style>
        /* Keyframes */
        @keyframes float-slow {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-15px);
            }
        }

        @keyframes float-medium {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes float-fast {

            0%,
            100% {
                transform: translateY(0) rotate(6deg);
            }

            50% {
                transform: translateY(-8px) rotate(6deg);
            }
        }

        @keyframes float-delayed {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(20px);
            }
        }

        /* Dynamic Bar Height Animations */
        @keyframes bar-height-1 {

            0%,
            100% {
                height: 40%;
            }

            50% {
                height: 75%;
            }
        }

        @keyframes bar-height-2 {

            0%,
            100% {
                height: 70%;
            }

            50% {
                height: 35%;
            }
        }

        @keyframes bar-height-3 {

            0%,
            100% {
                height: 50%;
            }

            50% {
                height: 85%;
            }
        }

        @keyframes bar-height-4 {

            0%,
            100% {
                height: 90%;
            }

            50% {
                height: 45%;
            }
        }

        @keyframes bar-height-5 {

            0%,
            100% {
                height: 60%;
            }

            50% {
                height: 80%;
            }
        }

        /* Pie Chart Draw Animation */
        @keyframes draw-circle {
            0% {
                stroke-dasharray: 0 251;
            }

            100% {
                stroke-dasharray: 180 251;
            }
        }

        @keyframes draw-circle-2 {
            0% {
                stroke-dasharray: 0 251;
            }

            100% {
                stroke-dasharray: 50 251;
            }
        }

        /* Utility Classes */
        .animate-float-slow {
            animation: float-slow 7s ease-in-out infinite;
        }

        .animate-float-medium {
            animation: float-medium 5s ease-in-out infinite;
            animation-delay: 1s;
        }

        .animate-float-fast {
            animation: float-fast 4s ease-in-out infinite;
            animation-delay: 0.5s;
        }

        .animate-float-delayed {
            animation: float-delayed 10s ease-in-out infinite;
        }

        .animate-bar-height-1 {
            animation: bar-height-1 3s ease-in-out infinite;
        }

        .animate-bar-height-2 {
            animation: bar-height-2 4s ease-in-out infinite;
            animation-delay: 0.2s;
        }

        .animate-bar-height-3 {
            animation: bar-height-3 3.5s ease-in-out infinite;
            animation-delay: 0.4s;
        }

        .animate-bar-height-4 {
            animation: bar-height-4 4.5s ease-in-out infinite;
            animation-delay: 0.1s;
        }

        .animate-bar-height-5 {
            animation: bar-height-5 3.2s ease-in-out infinite;
            animation-delay: 0.3s;
        }

        .animate-draw-circle {
            animation: draw-circle 2s ease-out forwards;
        }

        .animate-draw-circle-2 {
            animation: draw-circle-2 2.5s ease-out forwards;
            animation-delay: 0.5s;
        }

        .perspective-1000 {
            perspective: 1000px;
        }
    </style>
</head>

<body class="font-sans antialiased text-gray-900 bg-gray-100">

    <!-- Main Container: Full Screen Green Gradient -->
    <div
        class="min-h-screen relative flex items-center justify-center bg-gradient-to-br from-[#047857] via-[#065f46] to-[#064e3b] overflow-hidden font-sans selection:bg-emerald-500 selection:text-white">

        <!-- ============================================== -->
        <!-- BACKGROUND ANIMATED WIDGETS (Floating Layer) -->
        <!-- ============================================== -->

        <!-- 1. Floating Animated Bar Chart (Top Left) -->
        <div
            class="absolute top-16 left-6 lg:left-16 bg-white/10 backdrop-blur-md border border-white/20 p-5 rounded-2xl shadow-2xl animate-float-slow hidden lg:block w-56 z-0">
            <div class="flex justify-between items-center mb-4">
                <div class="h-2.5 w-20 bg-white/40 rounded-full"></div>
                <div class="flex gap-1">
                    <div class="h-1.5 w-1.5 bg-emerald-300 rounded-full animate-pulse"></div>
                    <div class="h-1.5 w-1.5 bg-emerald-300/50 rounded-full"></div>
                </div>
            </div>
            <div class="flex items-end justify-between gap-2 h-28 px-1">
                <div class="w-full bg-emerald-400/90 rounded-t-sm animate-bar-height-1"></div>
                <div class="w-full bg-emerald-300/90 rounded-t-sm animate-bar-height-2"></div>
                <div class="w-full bg-emerald-200/90 rounded-t-sm animate-bar-height-3"></div>
                <div
                    class="w-full bg-white/90 rounded-t-sm shadow-[0_0_15px_rgba(255,255,255,0.4)] animate-bar-height-4">
                </div>
                <div class="w-full bg-emerald-500/80 rounded-t-sm animate-bar-height-5"></div>
            </div>
        </div>

        <!-- 2. Floating Animated Pie Chart (Below Bar Chart) -->
        <div
            class="absolute top-80 left-12 lg:left-24 bg-white/10 backdrop-blur-md border border-white/20 p-2 rounded-full shadow-2xl animate-float-delayed hidden lg:flex items-center justify-center w-40 h-40 z-0">
            <div class="absolute inset-0 border-[3px] border-white/5 rounded-full m-1"></div>
            <div class="relative w-full h-full p-3">
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="rgba(255,255,255,0.1)"
                        stroke-width="12" />
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#34d399" stroke-width="12"
                        stroke-dasharray="180 251" stroke-linecap="round" class="animate-draw-circle origin-center" />
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#059669" stroke-width="12"
                        stroke-dasharray="50 251" stroke-dashoffset="-190" stroke-linecap="round"
                        class="animate-draw-circle-2 origin-center" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-white">
                    <span
                        class="text-xs font-medium text-emerald-100/80 tracking-widest uppercase text-[10px]">ERR</span>
                    <span class="text-xl font-black tracking-tight drop-shadow-md">404</span>
                </div>
            </div>
        </div>

        <!-- 3. Floating User Avatars (Bottom Right) -->
        <div
            class="absolute bottom-16 right-8 lg:right-16 bg-white/10 backdrop-blur-md border border-white/20 px-6 py-4 rounded-2xl shadow-2xl animate-float-medium hidden lg:flex items-center gap-3 z-0">
            <div class="flex -space-x-4">
                <div
                    class="w-10 h-10 rounded-full bg-yellow-200 border-2 border-emerald-800 shadow-sm flex items-center justify-center text-xs font-bold text-yellow-700">
                    JS</div>
                <div
                    class="w-10 h-10 rounded-full bg-blue-200 border-2 border-emerald-800 shadow-sm flex items-center justify-center text-xs font-bold text-blue-700">
                    AD</div>
                <div
                    class="w-10 h-10 rounded-full bg-white border-2 border-emerald-800 shadow-sm flex items-center justify-center text-xs font-bold text-gray-700">
                    +3</div>
            </div>
            <div class="text-white text-xs font-medium">
                <div>Active Users</div>
                <div class="text-emerald-300">Currently Online</div>
            </div>
        </div>

        <!-- 4. Floating Line Chart (Top Right) -->
        <div
            class="absolute top-24 right-8 lg:right-32 bg-white/10 backdrop-blur-md border border-white/20 p-4 rounded-xl shadow-2xl animate-float-fast hidden lg:block w-40 transform rotate-6 z-0">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-3 h-3 rounded-full bg-red-400 animate-pulse"></div>
                <span class="text-[10px] text-white/80 font-mono tracking-wider">NOT FOUND</span>
            </div>
            <svg class="w-full h-12 text-emerald-300 opacity-50" viewBox="0 0 100 40" fill="none" stroke="currentColor"
                stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M0 20 H 100" />
            </svg>
        </div>

        <!-- Abstract Particles -->
        <div
            class="absolute top-1/2 left-10 w-4 h-4 bg-yellow-400 rounded-full blur-sm animate-float-delayed opacity-60">
        </div>
        <div class="absolute bottom-40 left-1/4 w-2 h-2 bg-white rounded-full blur-[1px] animate-ping opacity-40"></div>
        <div class="absolute top-20 right-1/3 w-20 h-20 bg-emerald-400 rounded-full blur-3xl opacity-20"></div>

        <!-- ============================================== -->
        <!-- CENTERED CARD -->
        <!-- ============================================== -->
        <div
            class="relative z-20 w-full max-w-[480px] bg-white rounded-[2rem] shadow-[0_20px_70px_-10px_rgba(0,0,0,0.3)] p-8 md:p-12 m-4 text-center">

            <div class="flex flex-col items-center mb-8">
                <!-- Logo -->
                <div
                    class="w-20 h-20 bg-emerald-50 rounded-2xl flex items-center justify-center mb-6 shadow-sm border border-emerald-100 transform hover:scale-105 transition-transform duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-emerald-600" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-black text-gray-800 tracking-tighter mb-2">404</h1>
                <h2 class="text-2xl font-bold text-gray-600">Page Not Found</h2>
                <p class="text-gray-400 text-sm mt-4 leading-relaxed max-w-xs mx-auto">
                    Whoops! Use the map or go back to the dashboard. The page you are looking for has vanished.
                </p>
            </div>

            <!-- Action Button -->
            <a href="/"
                class="inline-block w-full py-3.5 px-4 bg-[#4f46e5] hover:bg-[#4338ca] text-white font-semibold rounded-xl shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.23)] transform active:scale-95 transition-all duration-200 no-underline uppercase tracking-wider text-sm">
                Back to Dashboard
            </a>

        </div>

        <!-- Copyright -->
        <div class="absolute bottom-6 text-emerald-100/60 text-xs tracking-widest font-light">
            &copy; 2025 Created <span class="font-semibold text-emerald-300">Agus Prasetya</span>
        </div>

    </div>
</body>

</html>