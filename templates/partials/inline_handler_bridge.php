<?php
/**
 * Output-buffer transform that converts inline event attributes into nonce-protected listeners.
 */
if (!function_exists('tpl_inline_handler_transform_start')) {
    function tpl_inline_handler_transform_start(): void
    {
        if (!tpl_inline_handler_transform_enabled()) {
            return;
        }

        static $started = false;
        if ($started) {
            return;
        }
        $started = true;
        ob_start('tpl_inline_handler_transform_buffer');
    }
}

if (!function_exists('tpl_inline_handler_transform_buffer')) {
    function tpl_inline_handler_transform_buffer(string $html): string
    {
        if (!tpl_inline_handler_transform_enabled() || trim($html) === '') {
            return $html;
        }

        if (!class_exists('DOMDocument')) {
            return $html;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (!$loaded) {
        return $html;
        }

        $handlerMap = [];
        $nodes = $dom->getElementsByTagName('*');
        foreach ($nodes as $node) {
            if (!$node->hasAttributes()) {
                continue;
            }

            $attributeNames = [];
            foreach ($node->attributes as $attr) {
                $attributeNames[] = $attr->name;
            }

            foreach ($attributeNames as $attrName) {
                if (stripos($attrName, 'on') !== 0) {
                    continue;
                }

                $event = strtolower(substr($attrName, 2));
                if ($event === '') {
                    continue;
                }

                $code = $node->getAttribute($attrName);
                $node->removeAttribute($attrName);
                if ($code === '') {
                    continue;
                }

                $handlerId = $node->getAttribute('data-tpl-handler-id');
                if ($handlerId === '') {
                    $handlerId = 'tpl-handler-' . bin2hex(random_bytes(6));
                    $node->setAttribute('data-tpl-handler-id', $handlerId);
                }

                if (!isset($handlerMap[$handlerId])) {
                    $handlerMap[$handlerId] = [
                        'events' => [],
                    ];
                }

                $handlerMap[$handlerId]['events'][] = [
                    'event' => $event,
                    'code' => $code,
                ];
            }
        }

        $output = $dom->saveHTML();

        $injected = '';
        if (!empty($handlerMap)) {
            $scriptBlock = tpl_inline_handler_script_block($handlerMap);
            if ($scriptBlock !== '') {
                $injected .= $scriptBlock;
            }
        }

        if ($injected !== '') {
            if (stripos($output, '</body>') !== false) {
                $output = preg_replace('/<\/body>/i', $injected . '</body>', $output, 1);
            } else {
                $output .= $injected;
            }
        }

        return $output;
    }
}

if (!function_exists('tpl_inline_handler_script_block')) {
    function tpl_inline_handler_script_block(array $handlerMap): string
    {
        if (empty($handlerMap)) {
            return '';
        }

        $nonceAttr = function_exists('tpl_script_nonce_attr') ? tpl_script_nonce_attr() : '';
        $script = '<script' . $nonceAttr . '>(function(){';

        foreach ($handlerMap as $handlerId => $payload) {
            $selector = '[data-tpl-handler-id="' . $handlerId . '"]';
            $selectorJson = json_encode($selector, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $events = $payload['events'] ?? [];
            foreach ($events as $definition) {
                $eventName = isset($definition['event']) ? json_encode($definition['event'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
                $code = $definition['code'] ?? '';
                if ($eventName === null || $code === '') {
                    continue;
                }
                $codeSanitized = str_replace('</script', '<\/script', $code);
                $script .= "document.querySelectorAll({$selectorJson}).forEach(function(node){node.addEventListener({$eventName},function(event){try{{$codeSanitized}}catch(handlerError){console.error('Inline handler error:',handlerError);}});});";
            }
        }

        $script .= '})();</script>';
        return $script;
    }
}

if (!function_exists('tpl_inline_handler_transform_end')) {
    function tpl_inline_handler_transform_end(): void
    {
        if (!tpl_inline_handler_transform_enabled()) {
            return;
        }

        static $ended = false;
        if ($ended) {
            return;
        }
        $ended = true;
        
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
}

if (!function_exists('tpl_js_escaped')) {
    /**
     * Güvenli JS string çıktısı üretir (JSON + HTML escape).
     */
    function tpl_js_escaped($value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_HEX_AMP
        );

        if ($json === false) {
            $json = 'null';
        }

        return htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

