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
    | Word List Path
    |--------------------------------------------------------------------------
    |
    | Path to the word list file. Each line should contain a word, optionally
    | preceded by a numeric index and whitespace (like the EFF format).
    |
    | Set to null to use the bundled EFF long word list (7776 words).
    | Or provide an absolute path to your own word list file.
    |
    */
    'word_list_path' => null,

];
