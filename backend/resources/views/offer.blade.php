<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Special Offer — RunMyPrint</title>
    <link rel="icon" type="image/svg+xml" href="/storage/brand/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    {{-- Factors.ai (faitracker) — offer page only --}}
    <script>window.faitracker=window.faitracker||function(){this.q=[];var t=new CustomEvent("FAITRACKER_QUEUED_EVENT");return this.init=function(t,e,a){this.TOKEN=t,this.INIT_PARAMS=e,this.INIT_CALLBACK=a,window.dispatchEvent(new CustomEvent("FAITRACKER_INIT_EVENT"))},this.call=function(){var e={k:"",a:[]};if(arguments&&arguments.length>=1){for(var a=1;a<arguments.length;a++)e.a.push(arguments[a]);e.k=arguments[0]}this.q.push(e),window.dispatchEvent(t)},this.message=function(){window.addEventListener("message",function(t){"faitracker"===t.data.origin&&this.call("message",t.data.type,t.data.message)})},this.message(),this.init("ybrozmi4g2fs9pptet9whcpq2p3kwod7",{host:"https://api.factors.ai"}),this}(),function(){var t=document.createElement("script");t.type="text/javascript",t.src="https://app.factors.ai/assets/factors.js",t.async=!0,(d=document.getElementsByTagName("script")[0]).parentNode.insertBefore(t,d)}();</script>
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <header class="bg-navy">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <a href="/"><img src="/storage/brand/logo.svg" alt="runmyprint" class="h-12 w-auto" /></a>
            <span class="text-sm font-medium text-white/75">Limited-time offer</span>
        </div>
    </header>

    <section class="bg-navy text-white">
        <div class="mx-auto max-w-5xl px-6 pb-20 pt-8 text-center">
            <p class="text-sm font-semibold uppercase tracking-widest text-lime-accent">Lorem ipsum dolor</p>
            <h1 class="mx-auto mt-4 max-w-3xl font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit
            </h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg text-white/75">
                Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.
            </p>
            <a href="/category/business-cards" class="mt-8 inline-block bg-lime-accent px-8 py-4 font-semibold text-navy shadow-lg shadow-lime-accent/20 transition hover:brightness-95">
                Lorem ipsum
            </a>
        </div>
    </section>

    <main class="mx-auto max-w-3xl px-6 py-16">
        <h2 class="font-display text-2xl font-bold tracking-tight">Lorem ipsum dolor sit amet</h2>
        <p class="mt-4 leading-relaxed text-ink/70">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore
            magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
        </p>
        <p class="mt-4 leading-relaxed text-ink/70">
            Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.
        </p>

        <h2 class="mt-10 font-display text-2xl font-bold tracking-tight">Sed ut perspiciatis</h2>
        <p class="mt-4 leading-relaxed text-ink/70">
            Totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt
            explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.
        </p>
        <ul class="mt-4 list-disc space-y-2 pl-6 text-ink/70">
            <li>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</li>
            <li>Sed do eiusmod tempor incididunt ut labore et dolore.</li>
            <li>Ut enim ad minim veniam, quis nostrud exercitation.</li>
            <li>Duis aute irure dolor in reprehenderit in voluptate.</li>
        </ul>
        <p class="mt-4 leading-relaxed text-ink/70">
            Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non
            numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.
        </p>
    </main>

    <footer class="border-t border-paper-300 py-8 text-center text-sm text-ink/50">
        © {{ date('Y') }} RunMyPrint. All rights reserved.
    </footer>
</body>
</html>
