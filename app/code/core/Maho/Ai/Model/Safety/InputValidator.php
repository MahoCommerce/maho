<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Safety_InputValidator
{
    /**
     * Patterns that indicate prompt injection attempts
     */
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?(previous|prior|above|earlier)\s+instructions?/i',
        '/disregard\s+(all\s+)?(previous|prior|above|earlier)\s+instructions?/i',
        '/forget\s+(all\s+)?(previous|prior|above|earlier)\s+instructions?/i',
        '/you\s+are\s+now\s+(a|an)\s+/i',
        '/act\s+as\s+(if\s+you\s+are\s+)?(a|an)\s+/i',
        '/pretend\s+(you\s+are|to\s+be)\s+/i',
        '/jailbreak/i',
        '/dan\s+mode/i',
        '/developer\s+mode/i',
        '/\[system\]/i',
        '/\[assistant\]/i',
        '/\<\|system\|\>/i',
        '/\<\|assistant\|\>/i',
        '/###\s*system/i',
        '/###\s*assistant/i',
    ];

    /**
     * "Ignore the previous instructions" in other languages: a verb, then the instructions, in the
     * order of the language. A message that only names the previous instructions passes.
     */
    private const IGNORE_INSTRUCTIONS_PATTERNS = [
        'it' => '/(ignora|ignorate|ignori|dimentica|dimenticate|dimentichi|trascura|trascurate).{0,40}((istruzioni|indicazioni)\s+(precedenti|di\s+prima|sopra)|precedenti\s+(istruzioni|indicazioni))/iu',
        'fr' => '/(ignore[rz]?|oublie[rz]?|ne\s+tiens\s+pas\s+compte|ne\s+tenez\s+pas\s+compte).{0,40}((instructions|consignes)\s+(pr[ée]c[ée]dentes|ant[ée]rieures|ci-dessus)|pr[ée]c[ée]dentes\s+(instructions|consignes))/iu',
        'de' => '/(ignorier|vergiss|vergesst|vergessen\s+sie|missachte).{0,40}(vorherigen|bisherigen|obigen|vorigen|früheren)\s+(anweisungen|instruktionen|befehle)|(vorherigen|bisherigen|obigen|vorigen|früheren)\s+(anweisungen|instruktionen|befehle).{0,20}(ignorieren|vergessen|missachten)/iu',
        'es' => '/(ignora|ignore|ignorar|olvida|olvide|olvidar|olvídate|descarta|descarte|omite).{0,40}instrucciones\s+(anteriores|previas|de\s+arriba)/iu',
        'ca' => '/(ignora|ignoreu|oblida|oblideu).{0,40}instruccions\s+(anteriors|pr[eè]vies)/iu',
        'pt' => '/(ignor[ae]|ignorar|esque[cç][ae]|esquecer|desconsider[ae]).{0,40}instru[çc][õo]es\s+(anteriores|pr[ée]vias|acima)/iu',
        'ro' => '/(ignor[ăa]|ignora[țţt]i|uit[ăa]|uita[țţt]i).{0,40}instruc[țţt]iunile\s+(anterioare|precedente)/iu',
        'nl' => '/(negeer|vergeet).{0,40}(vorige|eerdere|voorgaande|bovenstaande)\s+(instructies|aanwijzingen|opdrachten)/iu',
        'sv' => '/(ignorera|glöm|bortse\s+från).{0,40}(tidigare|föregående|ovanstående)\s+(instruktioner|anvisningar)/iu',
        'da_no' => '/(ignorer|glem|se\s+bort\s+fra).{0,40}(tidligere|forrige|ovenstående)\s+(instruktioner|instruksjoner|instrukser|anvisninger)/iu',
        'fi' => '/(ohita|unohda|älä\s+välitä).{0,40}(aiemm|edelli|yllä\s+olev)\pL*\s+ohje/iu',
        'pl' => '/(zignoruj|ignoruj|zapomnij).{0,40}(poprzedni|wcze[sś]niejsz)\pL*\s+(instrukcj|polece[nń])/iu',
        'cs_sk' => '/(ignoruj|zapome[nň]|zabudni).{0,40}(p[řr]edchoz|p[řr]ede[šs]l|predch[áa]dzaj[úu]c|predo[šs]l)\pL*\s+(pokyn|instrukc|in[šs]trukci)/iu',
        'hr_sr_sl' => '/(zanemari|ignoriraj|ignori[šs]i|zaboravi|prezri|pozabi).{0,40}(prethodn|prej[šs]nj|predhodn)\pL*\s+(upute|uputstv|instrukcij|naredb|navodil|ukaz)/iu',
        'hu' => '/(figyelmen\s+kívül|felejtsd\s+el|felejtse\s+el).{0,40}(korábbi|előző|fenti)\s+utasítás|(korábbi|előző|fenti)\s+utasítás\pL*.{0,20}(figyelmen\s+kívül|felejtsd\s+el)/iu',
        'ru_uk_bg' => '/(игнорир|проигнорир|забудь|забыть|не\s+обращай\s+внимания|ігнор|проігнор|забрави).{0,40}(предыдущ|прежн|вышеуказанн|попередн|предишн)\pL*\s+(инструкц|указани|команд|інструкц|вказівк)/iu',
        'el' => '/(αγνόησε|αγνοήστε|ξέχνα|ξεχάστε).{0,40}προηγούμεν\pL*\s+οδηγ/iu',
        'tr' => '/(önceki|yukarıdaki)\s.{0,20}(talimat|komut)\pL*.{0,20}(yok\s+say|görmezden\s+gel|unut)/iu',
        'ar' => '/(تجاهل|انس|إنس).{0,40}(التعليمات|الأوامر|التوجيهات)\s+السابقة/u',
        'fa' => '/دستور\S*\s+قبلی.{0,20}(نادیده|فراموش)/u',
        'he' => '/(התעלם|התעלמי|התעלמו|שכח|שכחי|שכחו).{0,40}ה?הוראות\s+ה?קודמות/u',
        'hi' => '/(पिछले|पूर्व|ऊपर\s+के).{0,20}निर्देश.{0,30}(अनदेखा|नज़रअंदाज़|नजरअंदाज|भूल)/u',
        'zh' => '/(忽略|无视|忘记|忘掉|忽視|無視|忘記|不要理会|不要理會).{0,10}(之前|以上|上面|前面|先前|上述|此前|以前).{0,6}(指令|指示|说明|說明|提示)/u',
        'ja' => '/(以前|これまで|前|上記|先)の.{0,10}(指示|命令|指令).{0,10}(無視|忘れ)/u',
        'ko' => '/(이전|앞의|위의|앞선).{0,15}(지시|명령|지침).{0,10}(무시|잊)/u',
        'vi' => '/(bỏ\s+qua|quên|phớt\s+lờ).{0,40}(hướng\s+dẫn|chỉ\s+dẫn|chỉ\s+thị)\s+(trước|ở\s+trên)/iu',
        'id_ms' => '/(abaikan|lupakan).{0,40}(instruksi|perintah|arahan)\s+(sebelumnya|terdahulu|di\s+atas)/iu',
        'th' => '/(เพิกเฉย|ละเว้น|ไม่ต้องสนใจ|ลืม).{0,20}คำ(สั่ง|แนะนำ).{0,10}(ก่อนหน้า|ข้างต้น)/u',
    ];

    /**
     * Validate user input before sending to AI
     *
     * @return array{safe: bool, reason: string}
     */
    public function validate(string $input): array
    {
        if (!Mage::getStoreConfigFlag('ai/safety/injection_detection')) {
            return ['safe' => true, 'reason' => ''];
        }

        if ($this->matchesInjection($input)) {
            return [
                'safe'   => false,
                'reason' => 'Input contains a potential prompt injection pattern.',
            ];
        }

        // Check base64 encoded content that might hide instructions.
        // Decode only the matched substring(s), not the whole input — prose
        // with a single URL or hash shouldn't have unrelated characters
        // stripped and force-decoded as one giant base64 blob.
        if (preg_match_all('/[A-Za-z0-9+\/]{50,}={0,2}/', $input, $matches)) {
            foreach ($matches[0] as $candidate) {
                $decoded = base64_decode($candidate, true);
                if ($decoded === false || strlen($decoded) <= 20) {
                    continue;
                }
                if ($this->matchesInjection($decoded)) {
                    return [
                        'safe'   => false,
                        'reason' => 'Input contains base64-encoded prompt injection.',
                    ];
                }
            }
        }

        // Check custom blocked patterns from config
        $customPatterns = (string) Mage::getStoreConfig('ai/safety/blocked_patterns');
        if ($customPatterns) {
            foreach (array_filter(array_map(trim(...), explode("\n", $customPatterns))) as $pattern) {
                if (@preg_match($pattern, $input)) {
                    return [
                        'safe'   => false,
                        'reason' => 'Input matches a blocked pattern.',
                    ];
                }
            }
        }

        return ['safe' => true, 'reason' => ''];
    }

    private function matchesInjection(string $text): bool
    {
        return array_any([...self::INJECTION_PATTERNS, ...self::IGNORE_INSTRUCTIONS_PATTERNS], fn($pattern) => preg_match($pattern, $text));
    }
}
