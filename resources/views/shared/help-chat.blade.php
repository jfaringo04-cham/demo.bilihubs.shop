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
            ['Track my order', 'Mag-sign in sa buyer account para makita ang status ng order mo.', 'login', 'Sign in'],
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
             @if ($helpChatRole === 'buyer') data-orders-url="{{ route('orders.index', [], false) }}" @endif
             data-sign-in-url="{{ route('login', [], false) }}"
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
                'Track my order': /^(?:track(?:ing)?|order status)$|\b(?:track(?:ing)?|nasaan|saan na|status|dumating na)\b.*\b(?:order|parcel|package)\b|\b(?:order|parcel|package)\b.*\b(?:track(?:ing)?|status|nasaan|saan na)\b/,
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
                    addReply(shoppingHelp
                        ? 'Pumili ng help topic sa ibaba o sabihin ang pangalan ng produktong hinahanap mo.'
                        : 'Pumili ng help topic sa ibaba.');
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
            let lastSearch = null;
            let pendingBudget = null;
            let pendingDetail = null;
            const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            const price = product => peso.format(product.price_minor / 100);
            const remember = product => {
                selected = product;
                followUp = null;
            };
            const budgetAmount = '(\\d+(?:,\\d{3})*(?:\\.\\d{1,2})?)\\s*([kK])?';
            const currency = '(?:₱|php\\b|p(?=\\s*\\d))';
            const markedBudget = new RegExp(
                `(?:^|[ ,])(?:hanggang|hangang|max(?:imum)?|under|up to|below|within|budget(?: ko)?(?: ay| is| na)?)\\s*${currency}?\\s*${budgetAmount}(?![\\d,.a-z])(?:\\s*(?:pesos?|php))?`,
                'iu',
            );
            const trailingBudget = new RegExp(
                `(?:^|[ ,])${currency}\\s*${budgetAmount}(?:\\s+na)?(?:\\s+(?:lang|lamang|only))?(?:\\s+pala)?(?:\\s+(?:ang )?budget(?: ko)?)?(?=\\s*(?:(?:ba|po|kayo)\\s*)*$)`,
                'iu',
            );
            const budgetOnly = new RegExp(
                `^${budgetAmount}\\s*(?:pesos?\\s*)?(?:na\\s+)?(?:(?:lang|lamang|only)\\s+)?(?:pala\\s+)?(?:ang\\s+)?budget(?:\\s+ko)?$`,
                'iu',
            );
            const parseBudget = text => {
                const match = text.match(markedBudget) || text.match(trailingBudget) || text.match(budgetOnly);
                if (!match) return { maxPrice: null, productText: text };

                const pesos = Number(match[1].replace(/,/g, '')) * (match[2] ? 1000 : 1);
                const productText = (text.slice(0, match.index) + ' ' + text.slice(match.index + match[0].length))
                    .replace(/\s+/g, ' ')
                    .replace(/(?:^|\s)(?:ang budget ko|budget ko|lang|only)(?=\s*$|\s+ba\b)/i, ' ')
                    .trim();

                return { maxPrice: Math.round(pesos * 100), productText };
            };

            const catalogName = text => text
                .replace(/^(?:(?:mayroon|meron|may|mayroong|merong)(?:\s+(?:ba|bang|po|kayong|kayo)\b)*|do you (?:have|sell)|(?:hanap(?:in)?|naghahanap|search(?: for)?|looking for|gusto)(?:\s+ako)?(?:\s+ng)?)(?:\s+|$)/i, '')
                .replace(/^(?:ba|bang|po|kayo|ng)\s+/i, '')
                .replace(/(?:\s+(?:na|ba|po|kayo|lang|please))+$/i, '')
                .replace(/^(?:na|ba|po|kayo|lang|only)$/i, '')
                .trim();
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
                        if (!input.readOnly) chooseProduct(product, pendingDetail);
                    });
                    const view = document.createElement('a');
                    view.href = product.url;
                    view.textContent = 'View Product';
                    info.append(title, cost, pick, view);
                    if (product.seller_url) {
                        const seller = document.createElement('a');
                        seller.href = product.seller_url;
                        seller.textContent = 'Message Seller';
                        info.appendChild(seller);
                    }
                    card.append(image, info);
                    list.appendChild(card);
                });
                messages.appendChild(list);
                messages.scrollTop = messages.scrollHeight;
            };
            const searchCatalog = async (term, forSeller, detail = null, maxPrice = null) => {
                const submit = form.querySelector('button');
                submit.disabled = true;
                input.readOnly = true;
                try {
                    const url = new URL(chat.dataset.catalogUrl, window.location.href);
                    url.searchParams.set('format', 'help-chat');
                    url.searchParams.set(detail ? 'product_id' : 'search', term);
                    if (maxPrice !== null) url.searchParams.set('max_price_minor', maxPrice);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Catalog search failed');
                    const { products } = await response.json();
                    if (detail) {
                        const product = products[0];
                        if (!product) {
                            selected = null;
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
                            if (product.seller_url) links.push({ url: product.seller_url, label: 'Message Seller' });
                            addReply('Piliin ang variant sa product page para ma-check ang available na sizes at stock.', links);
                        } else if (detail.size) {
                            const sizeLabel = value => value.trim().toLowerCase().replace(/^size\s+/i, '');
                            const aliases = { small: 's', medium: 'm', large: 'l' };
                            const normalizeSize = value => aliases[sizeLabel(value)] ?? sizeLabel(value);
                            const size = product.sizes.find(option => normalizeSize(option.name) === normalizeSize(detail.size));
                            addReply(size
                                ? `Size ${size.name}: ${size.stock > 0 ? `${size.stock} in stock` : 'sold out'}.`
                                : 'Walang matching size na nakalista. Tingnan ang options sa product page.', links);
                        } else {
                            addReply(`May ${product.stock} na stock ang ${product.name}. Tingnan ang product page para sa size options.`, links);
                        }
                        return;
                    }

                    lastSearch = term;
                    results = products;
                    selected = products.length === 1 ? products[0] : null;
                    if (products.length) {
                        addReply(forSeller
                            ? 'Piliin ang item, tapos sabihin na gusto mong kausapin ang seller.'
                            : maxPrice === null
                                ? 'Piliin ang item sa ibaba o sabihin kung alin, gaya ng “yung una”.'
                                : `Ito ang mga may listed price na pasok sa ${peso.format(maxPrice / 100)}. Piliin ang item o sabihin ang “yung una”.`);
                        cards(products);
                    } else {
                        addReply(maxPrice === null
                            ? 'Wala akong nahanap na match. Subukan ang ibang spelling o product name.'
                            : `Walang “${term}” na pasok sa ${peso.format(maxPrice / 100)}. Puwede mong baguhin ang budget o product name.`);
                    }
                } catch {
                    addReply('May problema sa product search ngayon. Subukan ulit o buksan ang Products.',
                        [{ url: chat.dataset.catalogUrl, label: 'Open Products' }]);
                } finally {
                    submit.disabled = false;
                    input.readOnly = false;
                }
            };

            const runSearch = async (term, maxPrice, forSeller = false) => {
                if (term.length < 2 || term.length > 100) {
                    addReply('Maglagay ng product name na 2 hanggang 100 characters.');
                    return;
                }

                followUp = null;
                pendingBudget = null;
                pendingDetail = null;
                results = [];
                selected = null;
                await searchCatalog(term, forSeller, null, maxPrice);
            };

            const chooseProduct = async (product, detail = null) => {
                remember(product);
                pendingDetail = null;
                if (detail?.askSize) {
                    followUp = 'size';
                    addReply(`Anong size ng ${product.name} ang gusto mong i-check?`);
                } else if (detail) {
                    await searchCatalog(product.id, false, detail);
                } else {
                    addReply(`Napili mo ang ${product.name}. Puwede kang magtanong tungkol sa presyo, stock, o seller.`);
                }
            };

            const showOrderTracking = async (orderId = null) => {
                const signIn = [{ url: chat.dataset.signInUrl, label: 'Sign in' }];
                if (!chat.dataset.ordersUrl) {
                    addReply('Mag-sign in sa buyer account para makita ang status ng order mo.', signIn);
                    return;
                }

                const myOrders = [{ url: chat.dataset.ordersUrl, label: 'Open My Orders' }];
                const send = form.querySelector('button');
                send.disabled = true;
                input.readOnly = true;

                try {
                    const url = new URL(chat.dataset.ordersUrl, window.location.href);
                    url.searchParams.set('format', 'help-chat');
                    if (orderId !== null) url.searchParams.set('order_id', orderId);

                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });

                    if (response.status === 401 || response.redirected) {
                        addReply('Mag-sign in ulit para ma-check ang orders mo.', signIn);
                        return;
                    }
                    if (response.status === 404 && orderId !== null) {
                        addReply('Hindi makita ang order na iyon. Buksan ang My Orders para sa listahan mo.', myOrders);
                        return;
                    }
                    if (!response.ok) throw new Error('Orders unavailable');

                    const { orders } = await response.json();
                    if (orderId !== null) {
                        const order = orders[0];
                        const delivery = order.delivery_status && order.delivery_status !== order.status
                            ? ` Delivery: ${order.delivery_status}.` : '';
                        addReply(`Order ${order.order_number}: ${order.status}.${delivery}`,
                            [{ url: order.tracking_url, label: 'View Tracking' }]);
                        return;
                    }

                    if (!orders.length) {
                        addReply('Wala ka pang order.', myOrders);
                        return;
                    }

                    addReply('Piliin ang order na gusto mong i-track.', myOrders);
                    const list = document.createElement('div');
                    list.className = 'bili-help__topics';
                    list.setAttribute('aria-label', 'Recent orders');
                    orders.forEach(order => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = order.order_number;
                        button.addEventListener('click', () => {
                            if (input.readOnly) return;
                            addMessage(order.order_number, 'bili-help__message');
                            showOrderTracking(order.id);
                        });
                        list.appendChild(button);
                    });
                    messages.appendChild(list);
                    messages.scrollTop = messages.scrollHeight;
                } catch {
                    addReply('Hindi ma-load ang order status ngayon. Subukan ulit o buksan ang My Orders.', myOrders);
                } finally {
                    send.disabled = false;
                    input.readOnly = false;
                }
            };

            topics.forEach(topic => topic.addEventListener('click', () => {
                if (input.readOnly) return;
                followUp = null;
                pendingBudget = null;
                pendingDetail = null;
                const question = topic.textContent.trim();
                addMessage(question, 'bili-help__message');
                if (question === 'Track my order') {
                    showOrderTracking();
                } else {
                    reply(topic);
                }
            }));

            form.addEventListener('submit', async event => {
                event.preventDefault();
                const question = input.value.trim();
                if (!question || input.readOnly) return;
                input.value = '';
                input.focus();
                addMessage(question, 'bili-help__message');

                const term = question.replace(/[?!.,]+$/, '').trim();
                const normalized = term.toLowerCase().replace(/[-–]/g, ' ')
                    .replace(/\b(accout|acount|acccount|accounnt|accountt)\b/g, 'account').replace(/\s+/g, ' ');
                const greeting = /^(?:(?:hi|hello|hey)(?:\s+po)?|good (?:morning|afternoon|evening)|kumusta(?:\s+po)?)$/i;
                const thanks = /^(?:salamat(?:\s+(?:po|nang marami))?|maraming salamat|thanks|thank you(?:\s+po)?)$/i;
                if (greeting.test(normalized) || thanks.test(normalized)) {
                    addReply(thanks.test(normalized) ? 'Walang anuman!' : 'Hi! Anong maitutulong ko sa iyo?');
                    return;
                }

                const matches = topics.filter(topic => keywords[topic.textContent.trim()]?.test(normalized));
                if (matches.length === 1 && matches[0].textContent.trim() === 'Track my order') {
                    await showOrderTracking();
                    return;
                }
                if (!shoppingHelp) {
                    reply(matches.length === 1 ? matches[0] : null);
                    return;
                }

                const sellerQuestion = /\b(?:message|contact|ask|tanong|kausapin|chat|makakausap)\b.*\bseller\b/i.test(term);
                const priceQuestion = /\b(magkano|price|presyo|how much)\b/.test(normalized);
                const shipping = /\b(shipping|delivery fee|padala)\b/.test(normalized);
                const sizeQuestion = /\b(?:size|sizes|sukat)\b/.test(normalized);
                const sizes = '(?:\\d{1,3}(?:\\.\\d{1,2})?|xxxs|xxs|xs|s|m|l|xl|xxl|xxxl|[2-5]xl|small|medium|large)';
                const requestedSize = term.match(new RegExp(`\\b(?:size|sukat)\\s+(${sizes})\\b`, 'i'))?.[1]
                    ?? (followUp === 'size' ? term.match(new RegExp(`^(${sizes})(?:\\s+po)?$`, 'i'))?.[1] : null);
                const stockQuestion = sizeQuestion || requestedSize || /\b(stock|available|sold out|may natira|mayroon pa|meron pa|may stock)\b/.test(normalized);
                const ordinals = ['una', 'pangalawa', 'pangatlo', 'pang apat', 'panglima'];
                const englishOrdinals = ['first', 'second', 'third', 'fourth', 'fifth'];
                const ordinal = normalized.match(/^(?:(?:yung|iyong|yong|the|option|number)\s+)?(una|pangalawa|pangatlo|pang apat|panglima|first|second|third|fourth|fifth|[1-5])\b/)?.[1];
                const position = ordinal ? ordinals.indexOf(ordinal) + 1 || englishOrdinals.indexOf(ordinal) + 1 || Number(ordinal) : 0;
                const { maxPrice, productText } = parseBudget(term);
                const productWithSize = requestedSize && sizeQuestion
                    ? productText.replace(new RegExp(`\\b(?:size|sukat)\\s+${sizes}\\b`, 'i'), '').trim()
                    : productText;
                const search = catalogName(productWithSize);
                const productRequest = search !== productWithSize.trim();
                const detail = sellerQuestion ? { kind: 'seller' }
                    : priceQuestion ? { kind: 'price' }
                    : stockQuestion ? { kind: 'stock', size: requestedSize, askSize: sizeQuestion && !requestedSize }
                    : null;

                if (position) {
                    const item = results[position - 1];
                    if (!item) {
                        addReply('Walang item sa numerong iyon. Pumili sa mga product card o maghanap ulit.');
                        return;
                    }

                    const next = detail ?? pendingDetail;
                    await chooseProduct(item, next);
                    return;
                }

                if (shipping && priceQuestion) {
                    addReply('Makikita ang shipping fee sa checkout kapag napili na ang delivery address.',
                        selected ? [{ url: selected.url, label: 'View Product' }] : []);
                    return;
                }

                if (maxPrice !== null) {
                    if (!Number.isSafeInteger(maxPrice) || maxPrice > 1000000000) {
                        addReply('Maglagay ng budget na nasa ₱0 hanggang ₱10,000,000.');
                    } else if (search) {
                        await runSearch(search, maxPrice);
                    } else if (lastSearch) {
                        await runSearch(lastSearch, maxPrice);
                    } else {
                        pendingBudget = maxPrice;
                        followUp = 'catalog';
                        addReply(`Anong produkto ang hinahanap mo sa budget na ${peso.format(maxPrice / 100)}?`);
                    }
                    return;
                }

                const refersToResults = !productRequest || !search
                    || /^(?:ito|iyan|nito|niyan|pa|stock|stock pa|available|available pa|sizes?|sukat)$/i.test(search);
                if (detail && selected && refersToResults) {
                    await chooseProduct(selected, detail);
                } else if (detail && results.length && refersToResults) {
                    pendingDetail = detail;
                    addReply('Alin sa mga item ang tinutukoy mo? Pindutin ang Select o sabihin ang “yung una”.');
                } else if (sellerQuestion) {
                    const link = document.querySelector('[data-help-seller-link]');
                    if (link) {
                        addReply('Buksan ang seller chat para ma-send mo ang tanong.',
                            [{ url: link.href, label: link.textContent.trim() }]);
                    } else {
                        followUp = 'seller';
                        addReply('Anong produkto ang gusto mong itanong sa seller? I-type ang pangalan.');
                    }
                } else if (matches.length === 1) {
                    followUp = null;
                    pendingBudget = null;
                    pendingDetail = null;
                    reply(matches[0]);
                } else if (detail && refersToResults) {
                    followUp = 'catalog';
                    addReply('Anong produkto ang tinutukoy mo? I-type ang pangalan.');
                } else if (search && (productRequest || ['catalog', 'seller'].includes(followUp) || (
                    /^[\p{L}\p{N}][\p{L}\p{N} &'+./-]*$/u.test(search)
                    && search.split(/\s+/).length <= 6
                    && !/^(?:paano|bakit|saan|sino|kailan|ano|pwede|puwede|how|why|where|who|when|what|can)\b/i.test(search)
                ))) {
                    await runSearch(search, pendingBudget, followUp === 'seller');
                    if (productRequest && requestedSize && results.length === 1) {
                        await chooseProduct(results[0], detail);
                    } else if (productRequest && requestedSize && results.length > 1) {
                        pendingDetail = detail;
                        addReply('Pumili ng item para ma-check ko ang size na hinahanap mo.');
                    }
                } else if (productRequest || /\b(product|produkto|hanap|search)\b/.test(normalized)) {
                    followUp = 'catalog';
                    addReply('Anong produkto ang hinahanap mo? I-type ang pangalan.');
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
