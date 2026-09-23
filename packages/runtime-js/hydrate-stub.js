/**
 * PART 3 THROWAWAY SCAFFOLDING — see docs/adr/0005-decouple-hydration-from-transpiler.md.
 *
 * A hand-written, single-component-specific hydration stub proving the
 * wiring (SSR HTML + embedded #reactiph-hydration manifest -> client
 * attaches to existing DOM without re-rendering from scratch) BEFORE the
 * real PHP->JS transpiler (Part 4) and generic reactive runtime (Part 5)
 * exist. This file is not meant to generalize to arbitrary components —
 * Part 5 replaces it entirely with transpiled-component-driven reactivity.
 */
(function () {
    'use strict';

    function readManifest() {
        var el = document.getElementById('reactiph-hydration');
        if (!el) {
            throw new Error('Reactiph: no #reactiph-hydration manifest found on the page.');
        }
        return JSON.parse(el.textContent);
    }

    // One hand-written hydrator per demo component, keyed by the
    // component's PHP class name as serialized in the manifest. A real
    // reactive runtime (Part 5) replaces this whole map with generic,
    // transpiled-component-driven behavior.
    var HYDRATORS = {
        Counter: function hydrateCounter(root, payload) {
            var countEl = root.querySelector('.count');
            var buttonEl = root.querySelector('.increment');
            var state = { count: payload.state.count };

            buttonEl.addEventListener('click', function () {
                state.count += 1;
                countEl.textContent = String(state.count);
            });
        },
    };

    function hydrate() {
        var payloads = readManifest();

        payloads.forEach(function (payload) {
            var root = document.querySelector('[data-reactiph-id="' + payload.id + '"]');
            if (!root) {
                console.error('Reactiph: no DOM node for hydration id', payload.id);
                return;
            }

            var hydrator = HYDRATORS[payload.component];
            if (!hydrator) {
                console.error('Reactiph: no hydrator registered for component', payload.component);
                return;
            }

            hydrator(root, payload);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrate);
    } else {
        hydrate();
    }
})();
