<?php

class HTML
{
    static private $colors = [
        'maroon', 'saddlebrown', 'mediumseagreen', 'indigo', 'darkorchid',
        'darkred', 'sienna', 'darkseagreen', 'darkslateblue', 'darkviolet',
        'brown', 'chocolate', 'darkolivegreen', 'slateblue', 'blueviolet',
        'firebrick', 'darkorange', 'olive', 'mediumslateblue', 'mediumpurple',
        'indianred', 'orange', 'olivedrab', 'cornflowerblue', 'purple',
        'lightcoral', 'sandybrown', 'yellowgreen', 'midnightblue', 'darkmagenta',
        'salmon', 'peru', 'darkgreen', 'navy', 'mediumorchid', 'tomato',
        'darkgoldenrod', 'green', 'darkblue', 'orchid', 'orangered',
        'goldenrod', 'forestgreen', 'mediumblue', 'violet', 'red',
        'coral', 'seagreen', 'blue', 'plum', 'crimson',
        'lightsalmon', 'limegreen', 'royalblue', 'fuchsia',
        'mediumvioletred', 'darksalmon', 'lime', 'dodgerblue', 'magenta',
        'palevioletred', 'burlywood', 'chartreuse', 'deepskyblue', 'thistle',
        'hotpink', 'tan', 'lawngreen', 'lightskyblue', 'lightpink',
        'deeppink', 'rosybrown', 'greenyellow', 'skyblue', 'pink',
        'navajowhite', 'darkkhaki', 'darkcyan', 'steelblue', 'peachpuff',
        'bisque', 'khaki', 'teal', 'cadetblue', 'mistyrose',
        'papayawhip', 'yellow', 'lightseagreen', 'mediumaquamarine', 'lavenderblush',
        'antiquewhite', 'gold', 'mediumturquoise', 'darkturquoise', 'seashell',
        'linen', 'palegoldenrod', 'turquoise', 'aqua', 'darkslategray',
        'oldlace', 'wheat', 'aquamarine', 'cyan', 'slategray',
        'floralwhite', 'moccasin', 'mediumspringgreen', 'navyblue', 'lightslategray',
        'snow', 'blanchedalmond', 'springgreen', 'lightsteelblue', 'dimgray',
        'whitesmoke', 'cornsilk', 'palegreen', 'lightblue', 'gray',
        'ghostwhite', 'lemonchiffon', 'lightgreen', 'powderblue', 'darkgray',
        'beige', 'lightgoldenrodyellow', 'honeydew', 'paleturquoise', 'silver',
        'lavender', 'lightyellow', 'mintcream', 'lightcyan', 'lightgrey',
        'white', 'ivory', 'aliceblue', 'azure', 'gainsboro',
    ];

    static private $tags = [
        'html' => [
            'body', 'head',
        ], 
        'metadata' => [
            'base', 'link', 'meta', 'style', 'title',
        ],
        'section' => [
            'address', 'article', 'article', 'footer', 'header', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'main', 'nav', 'section'
        ],
        'content' => [
            'blockquote', 'dd', 'div', 'dl', 'dt', 'figcaption', 'figure', 'hr', 'li', 'ol', 'p', 'pre', 'ul',
        ],
        'text' => [
            'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'kbd', 'mark', 'q',
            'rp', 'rt', 'ruby', 's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr', 'del', 'ins',
        ],
        'multimedia' => [
            'area', 'audio', 'img', 'map', 'track', 'video',
        ],
        'embed' => [
            'embed', 'iframe', 'object', 'param', 'picture', 'portal', 'source',
        ],
        'svg' => [
            'path', 'rect', 'circle', 'ellipse', 'line', 'polygon', 'polyline', 'text',
            'filters' => [
                'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix', 'feDiffuseLighting',
                'feDisplacementMap', 'feFlood', 'feGaussianBlur', 'feImage', 'feMerge', 'feMorphology', 'feOffset',
                'feSpecularLighting', 'feTile', 'feTurbulence', 'feDistantLight', 'fePointLight', 'feSpotLight',
            ],
        ],
        'math' => [
            
        ],
        'scripting' => [
            'canvas', 'noscript', 'script',
        ],
        'table' => [
            'table', 'caption', 'thead', 'tbody', 'tfoot', 'colgroup', 'col', 'th', 'tr', 'td',
        ],
        'forms' => [
            'form', 'input', 'label', 'select', 'optgroup', 'option', 'fieldset', 'legend',
            'button', 'textarea', 'progress', 'meter', 'output', 'datalist',
        ],   
        'interactive' => [
            'details', 'dialog', 'menu', 'summary',
        ],
        'component' => [
            'slot', 'template',
        ],
        'deprecated' => [
            'acronym', 'applet', 'basefont', 'bgsound', 'big', 'blink', 'center', 'content', 'dir', 'font', 'frame', 'frameset', 'hgroup', 'image',
            'keygen', 'marquee', 'menuitem', 'nobr', 'noembed', 'noframes', 'plaintext', 'rb', 'rtc', 'shadow', 'spacer', 'strike', 'tt', 'xmp',
        ],
    ];

    static private $styles = [
        'width' => [],
        'height' => [],
        'max-height' => [],
        'max-width' => [],
        'min-height' => [],
        'min-width' => [],
        'margin' => [
            'margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top',
        ],
        'padding' => [
            'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top',
        ],

        'display' => [],
        'top' => [],
        'right' => [],
        'bottom' => [],
        'left' => [],

        'flex' => [
            'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow', 'flex-shrink', 'flex-wrap',
        ],
        'align-items' => [],
        'align-self' => [],
        'justify-content' => [],

        'float' => [],

        'align-content' => [],
        'backface-visibility' => [],
        'background' => [
            'background',
            'background-attachment',
            'background-clip',
            'background-color',
            'background-image',
            'background-origin',
            'background-position',
            'background-repeat',
            'background-size',
        ],
        'border' => [
            'border',
            'border-bottom',
            'border-bottom-color',
            'border-bottom-left-radius',
            'border-bottom-right-radius',
            'border-bottom-style',
            'border-bottom-width',
            'border-collapse',
            'border-color',
            'border-image',
            'border-image-outset',
            'border-image-repeat',
            'border-image-slice',
            'border-image-source',
            'border-image-width',
            'border-left',
            'border-left-color',
            'border-left-style',
            'border-left-width',
            'border-radius',
            'border-right',
            'border-right-color',
            'border-right-style',
            'border-right-width',
            'border-spacing',
            'border-style',
            'border-top',
            'border-top-color',
            'border-top-left-radius',
            'border-top-right-radius',
            'border-top-style',
            'border-top-width',
            'border-width',
        ],

        'box-shadow' => [],
        'box-sizing' => [],

        'caption-side' => [],
        'clear' => [],
        'clip' => [],

        'column-count' => [],
        'column-fill' => [],
        'column-gap' => [],
        'column-rule' => [],
        'column-rule-color' => [],
        'column-rule-style' => [],
        'column-rule-width' => [],
        'column-span' => [],
        'column-width' => [],
        'columns' => [],

        'content' => [],
        'counter-increment' => [],
        'counter-reset' => [],
        'cursor' => [],
        'direction' => [],
        'empty-cells' => [],

        'font' => [
            'font',
            'font-family',
            'font-size',
            'font-size-adjust',
            'font-stretch',
            'font-style',
            'font-variant',
            'font-weight',
        ],
        'color' => [],

        'letter-spacing' => [],
        'line-height' => [],

        'list-style' => [],
        'list-style-image' => [],
        'list-style-position' => [],
        'list-style-type' => [],

        'opacity' => [],
        'order' => [],
        'outline' => [],
        'outline-color' => [],
        'outline-offset' => [],
        'outline-style' => [],
        'outline-width' => [],
        'overflow' => [],
        'overflow-x' => [],
        'overflow-y' => [],

        'page-break-after' => [],
        'page-break-before' => [],
        'page-break-inside' => [],
        'perspective' => [],
        'perspective-origin' => [],
        'position' => [],
        'quotes' => [],
        'resize' => [],
        'tab-size' => [],
        'table-layout' => [],

        'text-align' => [],
        'text-align-last' => [],
        'text-decoration' => [],
        'text-decoration-color' => [],
        'text-decoration-line' => [],
        'text-decoration-style' => [],
        'text-indent' => [],
        'text-justify' => [],
        'text-overflow' => [],
        'text-shadow' => [],
        'text-transform' => [],

        'animation' => [
            'animation',
            'animation-delay',
            'animation-direction',
            'animation-duration',
            'animation-fill-mode',
            'animation-iteration-count',
            'animation-name',
            'animation-play-state',
            'animation-timing-function',
        ],
        'transform' => [
            'transform',
            'transform-origin',
            'transform-style',
        ],
        'transition' => [
            'transition',
            'transition-delay',
            'transition-duration',
            'transition-property',
            'transition-timing-function',
        ],

        'vertical-align' => [],
        'visibility' => [],
        'white-space' => [],
        'word-break' => [],
        'word-spacing' => [],
        'word-wrap' => [],
        'z-index' => [],
    ];

    static function colors() {
        return HTML::$colors;
    }
}

