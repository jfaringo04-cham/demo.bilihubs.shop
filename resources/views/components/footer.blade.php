@props(['compact' => false])

@php
    $isAdmin = request()->routeIs('admin.*');

    $socialLinks = array_values(array_filter([
        ['label' => 'Facebook', 'url' => config('bilihub.social.facebook'), 'icon' => 'bi-facebook'],
        ['label' => 'Instagram', 'url' => config('bilihub.social.instagram'), 'icon' => 'bi-instagram'],
    ], fn ($social) => filled($social['url'])));

    $legalLinks = [
        ['label' => 'Privacy Policy', 'route' => 'privacy.policy'],
        ['label' => 'Terms & Conditions', 'route' => 'terms'],
        ['label' => 'Cookie Policy', 'route' => 'cookie.policy'],
    ];
@endphp

<footer class="bili-footer{{ $compact ? ' bili-footer-compact' : '' }}">
    <div class="container">

        <div class="bili-footer__top">
            <div>

                {{-- BRAND --}}
                <a href="{{ $isAdmin ? route('admin.dashboard') : route('home') }}"
                   class="bili-footer__brand">

                    <img
                        src="{{ asset('images/bilihublogo.png') }}"
                        alt="BiliHub"
                        class="bili-footer__logo"
                        width="44"
                        height="44"
                    >

                    <span class="bili-footer__brand-text">
                        <span class="bili-footer__name">BiliHub</span>

                        <span class="bili-footer__tagline">
                            @if($isAdmin)
                                Marketplace administration and management.
                            @else
                                {{ config('bilihub.tagline', 'Your marketplace, delivered.') }}
                            @endif
                        </span>
                    </span>

                </a>


                {{-- QUICK LINKS --}}
                <ul class="bili-footer__quick-links">

                    @if($isAdmin)

                        @if(Route::has('admin.dashboard'))
                            <li>
                                <a href="{{ route('admin.dashboard') }}">
                                    Dashboard
                                </a>
                            </li>
                        @endif

                        @if(Route::has('admin.reports'))
                            <li>
                                <a href="{{ route('admin.reports') }}">
                                    Reports
                                </a>
                            </li>
                        @endif

                        @if(Route::has('admin.settings'))
                            <li>
                                <a href="{{ route('admin.settings') }}">
                                    Settings
                                </a>
                            </li>
                        @endif

                    @else

                        <li>
                            <a href="{{ route('products.index') }}">
                                All Products
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('about') }}">
                                About Us
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('register') }}">
                                Become a Seller
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('apply.rider') }}">
                                Become a Rider
                            </a>
                        </li>

                    @endif

                </ul>

            </div>


            {{-- SOCIAL LINKS --}}
            @if(!$isAdmin && $socialLinks)

                <ul class="bili-footer__social">

                    @foreach($socialLinks as $social)

                        <li>
                            <a
                                href="{{ $social['url'] }}"
                                class="bili-footer__social-link"
                                aria-label="BiliHub on {{ $social['label'] }}"
                                title="{{ $social['label'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i
                                    class="bi {{ $social['icon'] }}"
                                    aria-hidden="true"
                                ></i>
                            </a>
                        </li>

                    @endforeach

                </ul>

            @endif

        </div>


        <hr class="bili-footer__divider">


        {{-- BOTTOM --}}
        <div class="bili-footer__bottom">

            <p class="bili-footer__copyright">
                &copy; {{ date('Y') }} BiliHub. All rights reserved.
            </p>


            <ul class="bili-footer__legal">

                @foreach($legalLinks as $link)

                    <li>

                        @if(Route::has($link['route']))

                            <a
                                class="bili-footer__legal-item"
                                href="{{ route($link['route']) }}"
                            >
                                {{ $link['label'] }}
                            </a>

                        @else

                            <span
                                class="bili-footer__legal-item is-pending"
                                aria-disabled="true"
                            >
                                {{ $link['label'] }}
                            </span>

                        @endif

                    </li>

                @endforeach

            </ul>

        </div>

    </div>
</footer>