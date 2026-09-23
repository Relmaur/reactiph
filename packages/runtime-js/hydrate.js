/**
 * Reactiph's generic hydration + event-delegation + DOM-patch client
 * runtime. Replaces Part 3's hand-written, single-component Counter stub
 * (docs/adr/0005-decouple-hydration-from-transpiler.md) — this file works
 * for any component whose methods and expressions ComponentTranspiler has
 * assembled onto window.ReactiphComponents, not just one hard-coded demo
 * component.
 *
 * Requires php-runtime.js to be loaded first (transpiled expressions call
 * its __phpString() helper).
 *
 * PART 5 SLICE 2 SCOPE (see docs/adr/0017-*.md): after a bound method runs,
 * each of the component's `{$expr}` text-position markers is recomputed
 * against the mutated instance and patched into the DOM directly — no
 * virtual-DOM diffing, no re-rendering the whole template. This only
 * covers text content; attribute values and structural (conditional/list)
 * markup are not patched (the current template subset has neither).
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

    function instantiate(payload, definition) {
        // A plain object carrying both state (own data properties) and
        // methods (own function properties) — calling instance.foo()
        // binds `this` to this same object, so this.otherMethod() calls
        // inside a transpiled method, and a transpiled expression reading
        // this.count, resolve correctly too.
        return Object.assign({}, payload.state, definition.methods);
    }

    /**
     * Finds the `<!--rN-->`/`<!--/rN-->` comment pair for one expression
     * index within `root`, and replaces everything between them with a
     * single fresh text node holding `value`. Skips descending into any
     * nested element carrying its own `data-reactiph-id` — that's a
     * separately hydrated component with its own instance and its own
     * marker numbering (marker indices are only unique per component
     * *class*, not page-wide), so walking into it here could match the
     * wrong expression.
     */
    function patchMarker(root, index, value) {
        var startData = 'r' + index;
        var endData = '/r' + index;
        var walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_COMMENT | NodeFilter.SHOW_ELEMENT,
            {
                acceptNode: function (node) {
                    if (
                        node.nodeType === Node.ELEMENT_NODE
                        && node !== root
                        && node.hasAttribute('data-reactiph-id')
                    ) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                },
            },
        );

        var startNode = null;
        var endNode = null;
        var node;
        while ((node = walker.nextNode())) {
            if (node.nodeType !== Node.COMMENT_NODE) {
                continue;
            }
            if (!startNode && node.data === startData) {
                startNode = node;
            } else if (startNode && node.data === endData) {
                endNode = node;
                break;
            }
        }

        if (!startNode || !endNode) {
            console.error('Reactiph: no marker pair found for expression', index);
            return;
        }

        var current = startNode.nextSibling;
        while (current && current !== endNode) {
            var toRemove = current;
            current = current.nextSibling;
            toRemove.parentNode.removeChild(toRemove);
        }

        endNode.parentNode.insertBefore(document.createTextNode(value), endNode);
    }

    function patchExpressions(root, definition, instance) {
        var expressions = definition.expressions || {};
        Object.keys(expressions).forEach(function (index) {
            var value = expressions[index].call(instance);
            patchMarker(root, index, value);
        });
    }

    function attachDelegation(root, instance, definition) {
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
            patchExpressions(root, definition, instance);

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

            var definition = window.ReactiphComponents && window.ReactiphComponents[payload.component];
            if (!definition) {
                console.error('Reactiph: no transpiled definition registered for component', payload.component);
                return;
            }

            var instance = instantiate(payload, definition);
            attachDelegation(root, instance, definition);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrate);
    } else {
        hydrate();
    }
})();
