<!DOCTYPE HTML>
<html lang="en">
    <head>
        <title>@yield('title')</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="/vendor/fontawesome/css/all.css" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css?family=Hind:400,500,700&display=swap" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="/css/normalize.css"/>
        <link rel="stylesheet" type="text/css" href="/css/site.css"/>
    </head>
    <body>
        <header>
            <div class="header-container">
                <img/>
            </div>
            <!--Mobile menu button-->
            <div class="header-container mobile">
                <div id="mobile-menu-button">
                    <span class="m-bar m-bar-1"></span>
                    <span class="m-bar m-bar-2"></span>
                    <span class="m-bar m-bar-2-b"></span>
                    <span class="m-bar m-bar-3"></span>
                </div>
            </div>

            <!-- Desktop menu -->
            <div class="header-container navigation-container">
                <ul id="navigation">
                    <li>
                        <a href="#" class="{{ $pageKey == 'features' ? 'active' : '' }}">Features</a>
                        <ul class="sub-menu">
                            <li>
                                <a href="{{ route('features-call-tracking') }}">
                                    <i class="fas fa-phone-volume"></i>
                                    <span>Call Tracking</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('features-analytics') }}">
                                    <i class="fas fa-chart-area"></i>
                                    <span>Analytics</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('features-reporting') }}">
                                    <i class="far fa-chart-bar"></i>                                    
                                    <span>Advanced Reporting</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="{{ route('pricing') }}" class="{{ $pageKey == 'pricing' ? 'active' : '' }}">Pricing</a>
                    </li>
                    <li>
                        <a href="{{ route('register') }}" class="{{ $pageKey == 'register' ? 'active' : '' }}">Create account</a>
                    </li>
                    <li>
                        <a href="{{ route('login') }}" class="{{ $pageKey == 'login' ? 'active' : '' }}">Login</a>
                    </li>
                </ul>
            </div>
        </header>
        <div  >

        </div>
        <footer>

        </footer>
        <script src="https://code.jquery.com/jquery-3.4.1.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
        <script>
            $('#mobile-menu-button').click(function(){
                $(this).toggleClass('open');
                $('.navigation-container').toggleClass('open');
            });
        </script>
        @yield('scripts')
    </body>
</html>