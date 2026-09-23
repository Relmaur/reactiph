/**
 * Reactiph's generic hydration + event-delegation client runtime.
 * Replaces Part 3's hand-written, single-component Counter stub
 * (docs/adr/0005-decouple-hydration-from-transpiler.md) — this file
 * works for any component whose methods ComponentTranspiler has
 * assembled onto window.ReactiphComponents, not just one hard-coded
 * demo component.
 *
 * PART 5 SLICE 1 SCOPE: wires (click)="method" bindings to real
 * transpiled method calls, proving the click -> transpiled-method-runs
 * pipeline end to end (see docs/adr/0016-*.md). Does NOT yet patch the
 * DOM after a state change — that's a deliberately separate, not-yet-
 * designed piece (see docs/STATUS.md's "Next up"). A state change is
 * only observable via console.log and a debug attribute this file
 * writes, until that's built.
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

    function instantiate(payload) {
        var definition = window.ReactiphComponents && window.ReactiphComponents[payload.component];
        if (!definition) {
            console.error('Reactiph: no transpiled definition registered for component', payload.component);
            return null;
        }

        // A plain object carrying both state (own data properties) and
        // methods (own function properties) — calling instance.foo()
        // binds `this` to this same object, so this.otherMethod() calls
        // inside a transpiled method resolve correctly too.
        return Object.assign({}, payload.state, definition.methods);
    }

    function attachDelegation(root, instance) {
        root.addEventListener('click', function (event) {
            // closest() searches all the way to the document root, not
            // just within `root` — contains() is what actually bounds
            // the match to this component's own subtree.
            var target = event.target.closest('[data-reactiph-on-click]');
            if (!target || !root.contains(target)) {
                return;
            }

            var methodName = target.getAttribute('data-reactiph-on-click');
            var method = instance[methodName];
            if (typeof method !== 'function') {
                console.error('Reactiph: no method "' + methodName + '" on this component instance.');
                return;
            }

            method.call(instance);

            // Slice 1 scope: prove the method ran and mutated state.
            // Does not patch the DOM yet.
            root.setAttribute('data-reactiph-debug-state', JSON.stringify(instance));
            console.log('Reactiph: ' + methodName + '() ran; new state:', instance);
        });
    }

    function hydrate() {
        var payloads = readManifest();

        payloads.forEach(function (payload) {
            var root = document.querySelector('[data-reactiph-id="' + payload.id + '"]');
            if (!root) {
                console.error('Reactiph: no DOM node for hydration id', payload.id);
                return;
            }

            var instance = instantiate(payload);
            if (instance) {
                attachDelegation(root, instance);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrate);
    } else {
        hydrate();
    }
})();
