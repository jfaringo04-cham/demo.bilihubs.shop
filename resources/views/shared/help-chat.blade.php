@php
    $helpChatUser = auth()->user();
    $helpChatRole = match (true) {
        $helpChatUser === null => 'guest',
        ($helpChatContext ?? null) === 'seller' && $helpChatUser->isSeller() => 'seller',
        ($helpChatContext ?? null) === 'rider' && $helpChatUser->isRider() => 'rider',
        $helpChatUser->isCustomer() => 'buyer',
        $helpChatUser->isSeller() => 'seller',
        $helpChatUser->isRider() => 'rider',
        default => null,
    };

    $helpChatTopics = match ($helpChatRole) {
        'buyer' => [
            ['Track my order', 'Sa My Orders, buksan ang order para makita ang status at tracking details.', 'orders.index', 'Open My Orders'],
            ['Cancel an order', 'Buksan ang order sa My Orders. Kung available ang Cancel Order, sundin ang instructions sa page.', 'orders.index', 'Open My Orders'],
            ['Return or refund', 'Buksan ang delivered order. Kung available ang Request Return, piliin ang item at quantity, tapos mag-upload ng photo kung damaged ang item.', 'orders.index', 'Open My Orders'],
            ['Contact support', 'Gumawa ng support ticket kung kailangan mo ng tulong sa order o account.', 'buyer.support.create', 'Create a ticket'],
        ],
        'seller' => [
            ['Add a product', 'Sa Add Product, ilagay ang details, presyo, stock, at photos bago i-save.', 'seller.products.create', 'Open Add Product'],
            ['Set packed weight', 'Sa Add o Edit Product, ilagay ang timbang ng isang piraso kasama ang packaging, in kg.', 'seller.products.create', 'Open Add Product'],
            ['Check payouts', 'Sa Seller Wallet, makikita ang balance at mga payout record.', 'seller.wallet.index', 'Open Seller Wallet'],
            ['Find my messages', 'Buksan ang Messages para makita at masagot ang conversations mo.', 'seller.messages.index', 'Open Messages'],
        ],
        'rider' => [
            ['Find my truck trip', 'Sa Truck Trips, buksan ang assigned manifest para makita ang ruta, scheduled departure, at status.', 'rider.truck-trip-history', 'Open Truck Trips'],
            ['Scan a parcel', 'Buksan ang assigned pickup. Kung may QR scan action, gamitin iyon para makumpirma ang parcel.', 'rider.pickups', 'Open Pickups'],
            ['Check wallet credits', 'Sa Rider Wallet, tingnan ang Wallet Transactions para makita ang credits na na-post na.', 'rider.wallet.index', 'Open Rider Wallet'],
            ['Find my messages', 'Buksan ang Messages para makita ang conversations mo.', 'rider.messages.index', 'Open Messages'],
        ],
        'guest' => [
            ['Create a buyer account', 'Sa Register, piliin ang buyer at sundin ang email verification steps.', 'register', 'Open Register'],
            ['Become a seller', 'Sa Register, piliin ang seller at ihanda ang details na hinihingi sa form.', 'register', 'Open Register'],
            ['Become a rider', 'Buksan ang rider application at sundin ang requirements sa form.', 'apply.rider', 'Open Rider Application'],
            ['Contact support', 'Mag-sign in sa buyer account para makagawa ng support ticket.', 'login', 'Sign in'],
        ],
        default => [],
    };
@endphp

@if ($helpChatTopics)
    <details class="bili-help" id="bili-help-chat" data-catalog-url="{{ route('products.index', [], false) }}"
             data-shopping-help="{{ in_array($helpChatRole, ['buyer', 'guest'], true) ? 'yes' : 'no' }}">
        <summary class="bili-help__launcher" aria-label="BiliHub Help Chat">
            <img class="bili-help__cat" src="{{ asset('images/bilihub-chat-cat.webp') }}" alt="" width="48" height="48">
        </summary>
        <section class="bili-help__panel" aria-labelledby="bili-help-heading">
            <header class="bili-help__header">
                <img class="bili-help__cat" src="{{ asset('images/bilihub-chat-cat.webp') }}" alt="" width="48" height="48">
                <h2 id="bili-help-heading">BiliHub Help Chat</h2>
                <button type="button" class="bili-help__close" aria-label="Close chat">×</button>
            </header>
            <div class="bili-help__messages" role="log" aria-label="Chat messages" aria-live="polite" tabindex="0">
                <p class="bili-help__reply">Hi! Paano kita matutulungan?</p>
            </div>
            <div class="bili-help__topics" aria-label="Suggested questions">
                @foreach ($helpChatTopics as [$question, $answer, $destination, $action])
                    <button type="button" data-answer="{{ $answer }}"
                        @if (\Illuminate\Support\Facades\Route::has($destination))
                            data-link="{{ route($destination) }}" data-action="{{ $action }}"
                        @endif
                    >{{ $question }}</button>
                @endforeach
            </div>
            <form class="bili-help__form">
                <input type="text" aria-label="Your question" placeholder="Type a question…" maxlength="240" required>
                <button type="submit">Send</button>
            </form>
        </section>
    </details>

    <style>
        .bili-help { position: fixed; right: max(1rem, env(safe-area-inset-right)); bottom: max(1rem, env(safe-area-inset-bottom)); z-index: 1035; color: #26183f; }
        .bili-help__launcher { display: grid; place-items: center; width: 4rem; height: 4rem; background: #f3eaff; border: 2px solid #b18af2; border-radius: 50%; box-shadow: 0 .3rem 1rem #29174b40; cursor: pointer; }
        .bili-help__launcher::-webkit-details-marker { display: none; }
        .bili-help__cat { display: block; width: 3rem; height: 3rem; object-fit: contain; flex-shrink: 0; }
        .bili-help__header .bili-help__cat { width: 2.5rem; height: 2.5rem; }
        .bili-help__panel { position: absolute; right: 0; bottom: calc(100% + .7rem); width: min(23rem, calc(100vw - 2rem)); max-height: calc(100dvh - 6rem); overflow: auto; background: #fff; border: 1px solid #e4d9f5; border-radius: 1rem; box-shadow: 0 .5rem 2rem #29174b40; }
        .bili-help__header { display: flex; gap: .65rem; align-items: center; padding: .8rem; background: #f2eaff; }
        .bili-help__header h2 { flex: 1; font-size: 1rem; margin: 0; font-weight: 700; }
        .bili-help__close { border: 0; background: transparent; color: inherit; font-size: 1.4rem; width: 2.5rem; height: 2.5rem; }
        .bili-help__messages { min-height: 6rem; max-height: min(15rem, 32dvh); overflow: auto; display: flex; flex-direction: column; gap: .5rem; padding: .8rem; }
        .bili-help__reply, .bili-help__message { max-width: 90%; margin: 0; padding: .55rem .75rem; border-radius: .75rem; overflow-wrap: anywhere; }
        .bili-help__reply { align-self: flex-start; background: #f2eaff; }
        .bili-help__reply a { display: block; margin-top: .25rem; color: #50219b; }
        .bili-help__message { align-self: flex-end; background: #632db6; color: white; }
        .bili-help__topics { display: flex; flex-wrap: wrap; gap: .35rem; padding: .25rem .8rem .7rem; }
        .bili-help__topics button { background: #fff; border: 1px solid #d6c3f2; color: #512799; border-radius: 1rem; padding: .35rem .6rem; font-size: .8rem; }
        .bili-help__form { display: flex; gap: .4rem; padding: .7rem; border-top: 1px solid #e8dff5; }
        .bili-help__form input { flex: 1; min-width: 0; padding: .45rem .6rem; border: 1px solid #cfc0e5; border-radius: .55rem; font: inherit; }
        .bili-help__form button { border: 0; border-radius: .55rem; padding: .45rem .75rem; background: #632db6; color: white; font-weight: 600; }
        .bili-help__cards { display: grid; gap: .5rem; }
        .bili-help__cards > div { display: flex; align-items: start; gap: .65rem; padding: .5rem; border: 1px solid #e4d9f5; border-radius: .65rem; }
        .bili-help__cards img { width: 3rem; height: 3rem; object-fit: cover; border-radius: .4rem; }
        .bili-help__cards button, .bili-help__cards a { margin-inline-end: .5rem; font-size: .85rem; }
        .bili-help :focus-visible { outline: 2px solid #864bd3; outline-offset: 2px; }
    </style>

    <script>
        (() => {
            const chat = document.getElementById('bili-help-chat');
            const launcher = chat.querySelector('.bili-help__launcher');
            const messages = chat.querySelector('.bili-help__messages');
            const form = chat.querySelector('form');
            const input = form.querySelector('input');
            const topics = [...chat.querySelectorAll('[data-answer]')];
            const keywords = {
                'Track my order': /\b(track(?:ing)?|nasaan|saan na|order status)\b/,
                'Cancel an order': /\b(cancel|cancellation|kansel)\b/,
                'Return or refund': /\b(return|refund|ibalik|sira|damaged|damage)\b/,
                'Contact support': /\b(support|ticket|contact|agent)\b/,
                'Add a product': /\b(add product|add a product|mag add|listing)\b/,
                'Set packed weight': /\b(weight|timbang|kilo|packaging)\b/,
                'Check payouts': /\b(payout|wallet|withdraw|kita)\b/,
                'Find my truck trip': /\b(truck|trip|manifest|linehaul)\b/,
                'Scan a parcel': /\b(scan|qr|pickup)\b/,
                'Check wallet credits': /\b(wallet|credit|earnings|kita)\b/,
                'Find my messages': /\b(message|messages|inbox)\b/,
                'Create a buyer account': /\b(register|sign up|buyer|gumawa ng account)\b/,
                'Become a seller': /\b(seller|magbenta|maging seller)\b/,
                'Become a rider': /\b(rider|delivery job)\b/,
            };

            const addMessage = (text, className) => {
                const bubble = document.createElement('p');
                bubble.className = className;
                bubble.textContent = text;
                messages.appendChild(bubble);
                return bubble;
            };

            const addReply = (text, links = []) => {
                const answer = addMessage(text, 'bili-help__reply');
                links.forEach(({ url, label }) => {
                    const link = document.createElement('a');
                    link.href = url;
                    link.textContent = label;
                    answer.appendChild(link);
                });
                messages.scrollTop = messages.scrollHeight;
            };

            const reply = (topic) => {
                if (!topic) {
                    addReply('Hindi ko pa masasagot iyan. Pumili ng topic sa ibaba.');
                    return;
                }
                addReply(topic.dataset.answer, topic.dataset.link
                    ? [{ url: topic.dataset.link, label: topic.dataset.action }]
                    : []);
            };

            const shoppingHelp = chat.dataset.shoppingHelp === 'yes';
            let followUp = null;
            let results = [];
            let selected = null;
            const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            const price = product => peso.format(product.price_minor / 100);
            const remember = product => {
                selected = product;
                followUp = null;
            };
            const cards = (products) => {
                const list = document.createElement('div');
                list.className = 'bili-help__cards';
                products.forEach((product, index) => {
                    const card = document.createElement('div');
                    const image = document.createElement('img');
                    image.src = product.image;
                    image.alt = '';
                    image.loading = 'lazy';
                    const info = document.createElement('div');
                    const title = document.createElement('strong');
                    title.textContent = `${index + 1}. ${product.name}`;
                    const cost = document.createElement('div');
                    cost.textContent = `${product.has_variants ? 'Listed price: ' : ''}${price(product)}`;
                    const pick = document.createElement('button');
                    pick.type = 'button';
                    pick.textContent = 'Select';
                    pick.addEventListener('click', () => {
                        remember(product);
                        addReply(`Napili mo ang ${product.name}. Puwede kang magtanong tungkol sa presyo, stock, o seller.`);
                    });
                    const view = document.createElement('a');
                    view.href = product.url;
                    view.textContent = 'View Product';
                    info.append(title, cost, pick, view);
                    card.append(image, info);
                    list.appendChild(card);
                });
                messages.appendChild(list);
                messages.scrollTop = messages.scrollHeight;
            };
            const searchCatalog = async (term, forSeller, detail = null) => {
                const submit = form.querySelector('button');
                submit.disabled = true;
                input.readOnly = true;
                try {
                    const url = new URL(chat.dataset.catalogUrl, window.location.href);
                    url.searchParams.set('format', 'help-chat');
                    url.searchParams.set(detail ? 'product_id' : 'search', term);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Catalog search failed');
                    const { products } = await response.json();
                    if (detail) {
                        const product = products[0];
                        if (!product) {
                            selected = null;
                            results = [];
                            addReply('Hindi na lumalabas ang item na ito sa catalog.');
                            return;
                        }
                        remember(product);
                        const links = [{ url: product.url, label: 'View Product' }];
                        if (detail.kind === 'seller') {
                            addReply('Buksan ang product page o ang seller chat para ma-send mo ang tanong.',
                                product.seller_url ? [{ url: product.seller_url, label: 'Message Seller' }] : links);
                        } else if (detail.kind === 'price') {
                            addReply(`Ang listed price ng ${product.name} ay ${price(product)}.${product.has_variants ? ' Maaaring iba ang presyo depende sa variant.' : ''}`, links);
                        } else if (product.on_vacation || product.stock <= 0) {
                            addReply(`${product.name}: ${product.on_vacation ? 'Temporarily unavailable ang shop.' : 'Sold out.'}`, links);
                        } else if (product.has_variants) {
                            addReply('Piliin ang variant sa product page para makita ang stock at sizes nito.', links);
                        } else if (detail.size) {
                            const size = product.sizes.find(option => option.name.trim().toLowerCase() === detail.size.toLowerCase());
                            addReply(size
                                ? `Size ${size.name}: ${size.stock > 0 ? `${size.stock} in stock` : 'sold out'}.`
                                : 'Walang matching size na nakalista. Tingnan ang options sa product page.', links);
                        } else {
                            addReply(`May ${product.stock} na stock ang ${product.name}. Tingnan ang product page para sa size options.`, links);
                        }
                        return;
                    }

                    results = products;
                    selected = products.length === 1 ? products[0] : null;
                    if (products.length) {
                        addReply(forSeller
                            ? 'Piliin ang item, tapos sabihin na gusto mong kausapin ang seller.'
                            : 'Piliin ang item sa ibaba o sabihin kung alin, gaya ng “yung una”.');
                        cards(products);
                    } else {
                        addReply('Wala akong nahanap na match. Subukan ang ibang spelling o product name.');
                    }
                } catch {
                    addReply('May problema sa product search ngayon. Subukan ulit o buksan ang Products.',
                        [{ url: chat.dataset.catalogUrl, label: 'Open Products' }]);
                } finally {
                    submit.disabled = false;
                    input.readOnly = false;
                }
            };

            topics.forEach(topic => topic.addEventListener('click', () => {
                followUp = null;
                addMessage(topic.textContent.trim(), 'bili-help__message');
                reply(topic);
            }));

            form.addEventListener('submit', async event => {
                event.preventDefault();
                const question = input.value.trim();
                if (!question || input.readOnly) return;
                input.value = '';
                input.focus();
                addMessage(question, 'bili-help__message');

                const normalized = question.toLowerCase()
                    .replace(/[-–]/g, ' ')
                    .replace(/\b(accout|acount|acccount|accounnt|accountt)\b/g, 'account')
                    .replace(/\s+/g, ' ');
                const term = question.replace(/[?!.]+$/, '').trim();
                const matches = topics.filter(topic => keywords[topic.textContent.trim()]?.test(normalized));
                const sellerQuestion = /\b(?:message|contact|ask|tanong|kausapin|chat)\b.*\bseller\b/.test(normalized);
                const productQuery = term.match(/^(?:(?:meron|mayroon|may)(?:\s+ba)?(?:\s+kayo)?(?:\s+bang)?|do you (?:have|sell)|(?:hanap(?:in)?|search(?: for)?|looking for)(?:\s+(?:ako ng|ng))?)\s+(.+)$/i);
                const catalogTerm = productQuery?.[1]?.replace(/\s+(?:ba(?: kayo)?|kayo)$/i, '').trim();

                const choice = normalized.match(/^(?:yung|iyong|the|option|number)?\s*(una|pangalawa|pangatlo|pang apat|panglima|first|second|third|fourth|fifth|[1-5])\b/);
                const position = choice && ['una', 'pangalawa', 'pangatlo', 'pang apat', 'panglima'].indexOf(choice[1]) + 1;
                const englishPosition = choice && ['first', 'second', 'third', 'fourth', 'fifth'].indexOf(choice[1]) + 1;
                const picked = choice && results[(position || englishPosition || Number(choice[1])) - 1];
                const size = term.match(/\bsize\s+(\d+(?:\.\d+)?|[a-z]+)\b/i)?.[1];
                const priceQuestion = /\b(magkano|price|presyo|how much)\b/.test(normalized);
                const shipping = /\b(shipping|delivery fee|padala)\b/.test(normalized);
                const stockQuestion = size || /\b(stock|available|sold out)\b/.test(normalized);

                if (shoppingHelp && picked) remember(picked);

                if (shoppingHelp && shipping && priceQuestion) {
                    addReply('Makikita ang shipping fee sa checkout kapag napili na ang delivery address.',
                        selected ? [{ url: selected.url, label: 'View Product' }] : []);
                } else if (shoppingHelp && selected && (sellerQuestion || priceQuestion || stockQuestion)) {
                    await searchCatalog(selected.id, false, {
                        kind: sellerQuestion ? 'seller' : priceQuestion ? 'price' : 'stock',
                        size,
                    });
                } else if (shoppingHelp && picked) {
                    addReply(`Napili mo ang ${picked.name}. Ano ang gusto mong malaman tungkol dito?`);
                } else if (shoppingHelp && !selected && results.length && (priceQuestion || stockQuestion)) {
                    addReply('Alin sa mga resulta ang tinutukoy mo? Pindutin ang Select o sabihin kung pang-ilan.');
                } else if (shoppingHelp && sellerQuestion) {
                    const link = document.querySelector('[data-help-seller-link]');
                    followUp = null;
                    if (link) {
                        addReply('Buksan ang seller chat para ma-type at ma-send mo ang tanong mo.',
                            [{ url: link.href, label: link.textContent.trim() }]);
                    } else {
                        followUp = 'seller';
                        addReply('Anong produkto ang gusto mong itanong sa seller? I-type ang product name.');
                    }
                } else if (matches.length === 1) {
                    followUp = null;
                    reply(matches[0]);
                } else if (shoppingHelp && (catalogTerm || followUp)) {
                    const forSeller = followUp === 'seller';
                    followUp = null;
                    const search = catalogTerm || term;
                    if (search.length >= 2 && search.length <= 100) {
                        await searchCatalog(search, forSeller);
                    } else {
                        addReply('Maglagay ng product name na 2 hanggang 100 characters.');
                    }
                } else if (shoppingHelp && /\b(product|produkto|hanap|search|mayroon|meron)\b/.test(normalized)) {
                    followUp = 'catalog';
                    addReply('Anong produkto ang hinahanap mo? I-type ang pangalan.');
                } else if (shoppingHelp && /^[\p{L}\p{N}][\p{L}\p{N} &'+./-]*$/u.test(term)
                    && term.length >= 2 && term.length <= 100
                    && term.split(/\s+/).length <= 6
                    && !/^(?:hi|hello|hey|salamat|thanks|thank you)[!.]?$/i.test(term)
                    && !/^(?:paano|bakit|saan|sino|kailan|ano|pwede|puwede|how|why|where|who|when|what|can)\b/i.test(term)) {
                    await searchCatalog(term, false);
                } else {
                    reply(null);
                }
            });

            const close = () => {
                chat.open = false;
                launcher.focus();
            };
            chat.querySelector('.bili-help__close').addEventListener('click', close);
            chat.addEventListener('keydown', event => {
                if (event.key === 'Escape' && chat.open) {
                    event.stopPropagation();
                    close();
                }
            });
        })();
    </script>
@endif
