<?php

declare(strict_types=1);

return [
    'livewire' => [
        'ignore_methods' => ['$refresh', '__dispatch', 'loadFacets', '$sync', 'poll'],
        'max_components' => 10,
        'max_calls'      => 20,
        'max_updates'    => 20,
    ],
];
