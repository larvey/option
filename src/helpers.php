<?php

/**
 * @return \Larvey\Option\OptionManager
 */
function options()
{
    return app('option');
}

/**
 * Bir seçenek adına göre bir seçenek değeri alın.
 *
 * @since 1.0
 *
 * @param string $key       Alınacak seçeneğin adı.
 * @param mixed  $default   İsteğe bağlı. Seçenek mevcut değilse döndürülecek varsayılan değer.
 *
 * @return mixed Seçenek için ayarlanan değeri döndürür.
 */
function get_option($key, $default = null)
{
    return options()->get($key, $default);
}

/**
 * Yeni bir seçenek ekler.
 *
 * @since 1.0
 *
 * @param string $key                Eklenecek seçeneğin adı.
 * @param mixed  $value              İsteğe bağlı. Seçenek değeri. Skaler değilse serileştirilebilir olmalıdır.
 * @param int    $autoload           İsteğe bağlı. Uygulama başladığında seçeneğin yüklenip yüklenmeyeceği.
 *                                   seçenekler için, `$autoload` sadece `update_option()` kullanılarak `$value` değiştirildiyse güncellenebilir.
 *
 * @return bool Seçenek eklenmediyse `false`, seçenek eklendiyse `true`.
 */
function add_option(string $key, $value = '', $autoload = 1): bool
{
    return options()->add($key, $value, $autoload);
}

/**
 * Daha önce eklenmiş olan bir seçeneğin değerini güncelleyin.
 *
 * @since 1.0
 *
 * @param string $key             Seçenek adı.
 * @param mixed  $value           Seçenek değer. Skaler değilse serileştirilebilir olmalıdır.
 * @param int    $autoload        İsteğe bağlı. Uygulama başladığında seçeneğin yüklenip yüklenmeyeceği.
 *                                Mevcut seçenekler için, `$autoload` sadece `update_option()` kullanılarak `$value` değiştirildiyse güncellenebilir.
 *
 * @return bool Değer güncellenmediyse `false`, değer güncellendiyse `true`.
 */
function update_option(string $key, $value, $autoload = null)
{
    return options()->update($key, $value, $autoload);
}

/**
 * Seçeneği ada göre siler. Uygulamanın korunan seçeneklerinin kaldırılmasını önler.
 *
 * @since 1.0
 *
 * @param string $key   Kaldırılacak seçeneğin adı.
 *
 * @return bool Seçenek başarıyla silinirse `true`, başarısızlıkta `false`.
 */
function delete_option(string $key)
{
    return options()->delete($key);
}

/**
 * Serileştirilmiş olup olmadığını bulmak için değeri kontrol edin.
 *
 * $data bir dize değilse, döndürülen değer her zaman yanlış olacaktır.
 * Serileştirilmiş veri her zaman bir dizedir.
 *
 * @param string $data     Serileştirilmiş olup olmadığını kontrol etmek için değer.
 * @param bool   $strict   İsteğe bağlı. Dizenin sonu hakkında katı olup olmadığı. Varsayılan true
 *
 * @return bool
 */
function is_serialized($data, $strict = true)
{
    // if it isn't a string, it isn't serialized.
    if (!is_string($data)) {
        return false;
    }
    $data = trim($data);
    if ('N;' == $data) {
        return true;
    }
    if (strlen($data) < 4) {
        return false;
    }
    if (':' !== $data[1]) {
        return false;
    }
    if ($strict) {
        $lastc = substr($data, -1);
        if (';' !== $lastc && '}' !== $lastc) {
            return false;
        }
    }
    else {
        $semicolon = strpos($data, ';');
        $brace = strpos($data, '}');
        // Either ; or } must exist.
        if (false === $semicolon && false === $brace) {
            return false;
        }
        // But neither must be in the first X characters.
        if (false !== $semicolon && $semicolon < 3) {
            return false;
        }
        if (false !== $brace && $brace < 4) {
            return false;
        }
    }
    $token = $data[0];
    switch ($token) {
        case 's':
            if ($strict) {
                if ('"' !== substr($data, -2, 1)) {
                    return false;
                }
            }
            elseif (false === strpos($data, '"')) {
                return false;
            }
        // or else fall through
        case 'a':
        case 'O':
            return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';

            return (bool) preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
    }

    return false;
}

/**
 * Serileştirilmiş verilerin dize türünde olup olmadığını kontrol edin.
 *
 * @param string $data   Serileştirilmiş veri.
 *
 * @return bool
 */
function is_serialized_string($data)
{
    // bir dize değilse, serileştirilmiş bir dize değildir.
    if (!is_string($data)) {
        return false;
    }
    $data = trim($data);
    if (strlen($data) < 4) {
        return false;
    }

    if (':' !== $data[1]) {
        return false;
    }

    if (';' !== substr($data, -1)) {
        return false;
    }

    if ($data[0] !== 's') {
        return false;
    }

    return !('"' !== $data[strlen($data) - 2]);
}

/**
 * Gerekirse verileri seri hale getirin.
 *
 * @param string|array|object $data   Serileştirilmiş olabilir.
 *
 * @return mixed Bir skaler veri
 *
 */
function maybe_serialize($data)
{
    if (is_array($data) || is_object($data)) {
        return serialize($data);
    }

    if (is_serialized($data, false)) {
        return serialize($data);
    }

    return $data;
}

/**
 * Yalnızca seri hale getirilmişse değeri seri hale getirin.
 *
 * @param string $original   Belki gerekirse seri hale getirilmemiş bir orijinal.
 *
 * @return mixed Sıralanmamış veri herhangi bir türde olabilir.
 */
function maybe_unserialize($original)
{
    // Seri hale getirilmemiş verileri seri hale getirmeye çalışmayın
    if (is_serialized($original)) {
        return @unserialize($original);
    }

    return $original;
}
