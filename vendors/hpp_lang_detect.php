<?php
/**
 * Eenvoudige heuristiek — vervang desgewenst met echte detectie.
 * Retourneer 2-letter code of '' als onbekend.
 */
function hpp_detect_language($text){
    $text = mb_strtolower(trim((string)$text), 'UTF-8');
    if($text === '') return '';

    // Heel simpele hints per taal
    // Nederlands
    if(preg_match('/\b(de|het|een|en|ik|jij|wij|niet|wel|alsjeblieft|vrijdag|zondag|bericht|test)\b/u', $text)){
        return 'nl';
    }
    // Engels
    if(preg_match('/\b(the|and|you|not|are|is|this|again|message|friday|sunday|test)\b/u', $text)){
        return 'en';
    }
    // Duits
    if(preg_match('/\b(der|die|das|und|ich|nicht|ein|wieder|freitag|sonntag|nachricht|test)\b/u', $text)){
        return 'de';
    }
    // Frans
    if(preg_match('/\b(le|la|les|et|je|vous|pas|bonjour|dimanche|vendredi|message|test)\b/u', $text)){
        return 'fr';
    }
    // Spaans
    if(preg_match('/\b(el|la|los|las|y|no|sí|hola|domingo|viernes|mensaje|prueba|test)\b/u', $text)){
        return 'es';
    }

    return '';
}
