<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Number of Words
    |--------------------------------------------------------------------------
    |
    | The default number of words to include in a generated passphrase.
    | Must be between 3 and 20.
    |
    */
    'num_words' => 3,

    /*
    |--------------------------------------------------------------------------
    | Word Separator
    |--------------------------------------------------------------------------
    |
    | The character(s) used to separate words in the generated passphrase.
    |
    */
    'word_separator' => '-',

    /*
    |--------------------------------------------------------------------------
    | Capitalize
    |--------------------------------------------------------------------------
    |
    | When true, the first letter of each word will be capitalized.
    |
    */
    'capitalize' => false,

    /*
    |--------------------------------------------------------------------------
    | Include Number
    |--------------------------------------------------------------------------
    |
    | When true, a random number (0-9) will be appended to the end of a
    | randomly selected word in the passphrase.
    |
    */
    'include_number' => false,

    /*
    |--------------------------------------------------------------------------
    | Word List
    |--------------------------------------------------------------------------
    |
    | Custom word list as a PHP array of strings.
    |
    | Set to null to use the bundled EFF long word list (7776 words).
    | Or provide your own list, for example: ['correct', 'horse', 'battery', 'staple']
    |
    */
    'word_list' => null,

];
