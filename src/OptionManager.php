<?php

namespace Larvey\Option;

class OptionManager
{
    /**
     * Veri tabanı bağlantısı örneği.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * Sorgulanacak tablo.
     *
     * @var string
     */
    protected $table;

    /**
     * @param \Illuminate\Database\Connection $connection
     * @param string                          $table
     */
    public function __construct(\Illuminate\Database\Connection $connection, $table = null)
    {
        $this->connection = $connection;
        $this->table = $table ?: 'options';
    }

    /**
     * Bir seçenek adına göre bir seçenek değeri alın.
     *
     * Seçenek yoksa veya bir değeri yoksa, dönüş değeri yanlış olacaktır. Bu, bir
     * seçenek kurmanız gerekip gerekmediğini kontrol etmek ve eklenti seçeneklerinin
     * yüklenmesi sırasında yaygın olarak kullanıldığını ve yükseltmenin gerekli olup
     * olmadığını sınamak için kullanışlıdır.
     *
     * @since 1.0
     *
     * @param string $key       Alınacak seçeneğin adı.
     * @param mixed  $default   İsteğe bağlı. Seçenek mevcut değilse döndürülecek varsayılan değer.
     *
     * @return mixed Seçenek için ayarlanan değeri döndürür.
     */
    public function get(string $key, $default = null)
    {
        $key = trim($key);
        if (empty($key)) {
            return false;
        }

        if (!app_installing()) {
            // prevent non-existent options from triggering multiple queries
            $notoptions = get_cache('notoptions', 'options');
            if (isset($notoptions[$key])) {

                return $default;
            }

            $alloptions = $this->loadOptions();

            // $key önbellekte mevcutsa değeri önbellekten al
            if (isset($alloptions[$key])) {
                $value = $alloptions[$key];
            }
            else {
                $value = get_cache($key, 'options');

                if ($value === null) {
                    $row = $this->newQuery()->select('value')->where('key', $key)->limit(1)->first();

                    // Has to be get_row instead of get_var because of funkiness with 0, false, null values
                    if (is_object($row)) {
                        $value = $row->value;
                        set_cache($key, $value, 'options');
                    }
                    else { // option does not exist, so we must cache its non-existence
                        if (!is_array($notoptions)) {
                            $notoptions = [];
                        }
                        $notoptions[$key] = true;
                        set_cache('notoptions', $notoptions, 'options');

                        return $default;
                    }
                }
            }
        }
        else {
            try {
                $row = $this->newQuery()->select('value')->where('key', $key)->limit(1)->first();
            }
            catch (\Exception $e) {
                $row = null;
            }

            if (is_object($row)) {
                $value = $row->value;
            }
            else {
                return $default;
            }
        }

        return maybe_unserialize($value);
    }

    /**
     * Yeni bir seçenek ekler.
     *
     * Değerleri serileştirmeniz gerekmez. Değerin serileştirilmesi gerekiyorsa, veritabanına
     * eklenmeden önce serileştirilir. Unutmayın, kaynaklar seri hale getirilemez veya bir seçenek
     * olarak eklenemez.
     *
     * Değersiz seçenekler oluşturabilir ve daha sonra değerleri güncelleyebilirsiniz. Mevcut seçenekler
     * güncellenmeyecek ve korumalı bir WordPress seçeneği eklemediğinizden emin olmak için kontroller
     * gerçekleştirilecektir. Seçeneklerin korunanlarla aynı şekilde adlandırılmamasına özen gösterilmelidir.
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
    public function add(string $key, $value = '', $autoload = 1)
    {
        $option = trim($key);
        if (empty($option)) {
            return false;
        }

        $this->protectOption($key);

        if (is_object($value)) {
            $value = clone $value;
        }

        $value = $this->sanitize($option, $value);

        // Seçeneğin mevcut olmadığından emin olun. Bir db sorgusu için sormadan önce 'notoptions' önbelleğini kontrol edebiliriz
        $notoptions = get_cache('notoptions', 'options');
        if (!is_array($notoptions) || !isset($notoptions[$option])) {

            if ($this->get($option) !== null) {
                return false;
            }
        }

        $serialized_value = maybe_serialize($value);
        $autoload = ($autoload === 0 || $autoload === false) ? 0 : 1;

        $result = $this->newQuery()->updateOrInsert([
            'key'      => $key,
            'value'    => $serialized_value,
            'autoload' => $autoload,
        ]);

        if (!$result) {
            return false;
        }

        if (!app_installing()) {
            if ($autoload === 1) {
                $alloptions = $this->loadOptions();
                $alloptions[$option] = $serialized_value;
                set_cache('alloptions', $alloptions, 'options');
            }
            else {
                set_cache($option, $serialized_value, 'options');
            }
        }

        // Bu seçenek şimdi var
        $notoptions = get_cache('notoptions', 'options'); // yes, again... we need it to be fresh
        if (is_array($notoptions) && isset($notoptions[$option])) {
            unset($notoptions[$option]);
            set_cache('notoptions', $notoptions, 'options');
        }

        return true;
    }

    /**
     * Daha önce eklenmiş olan bir seçeneğin değerini güncelleyin.
     *
     * Değerleri serileştirmeniz gerekmez. Değerin serileştirilmesi gerekiyorsa, veritabanına
     * eklenmeden önce serileştirilir. Unutmayın, kaynaklar seri hale getirilemez veya bir
     * seçenek olarak eklenemez.
     *
     * Eğer seçenek mevcut değilse, seçenek, `$autoload` değerinde 'true' olan seçenek değerine eklenecektir.
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
    public function update(string $key, $value, $autoload = null)
    {
        $key = trim($key);
        if (empty($key)) {
            return false;
        }

        $this->protectOption($key);

        if (is_object($value)) {
            $value = clone $value;
        }

        $value = $this->sanitize($key, $value);
        $old_value = $this->get($key);

        /*
         * Yeni ve eski değerler aynı ise, güncellemeye gerek yoktur.
         *
         * Serileştirilmemiş değerler çoğu durumda yeterli olacaktır. Serileştirilmemiş veriler
         * farklılık gösterirse, gereksiz veri tabanlarının aynı nesne örneklerini gerektirmemesi
         * için (belki) seri hale getirilmiş veriler kontrol edilir.
         *
         */
        if ($value === $old_value || maybe_serialize($value) === maybe_serialize($old_value)) {
            return false;
        }

        if ($old_value === null) {
            // Yeni seçenekler için varsayılan ayar açık.
            if ($autoload === null) {
                $autoload = 1;
            }

            return add_option($key, $value, '', $autoload);
        }

        $serialized_value = maybe_serialize($value);

        $data = [
            'value' => $serialized_value,
        ];

        if ($autoload !== null) {
            $data['autoload'] = ($autoload === 0 || $autoload === false) ? 0 : 1;
        }

        $result = $this->newQuery()->where('key', $key)->update($data);
        if (!$result) {
            return false;
        }

        $notoptions = get_cache('notoptions', 'options');
        if (is_array($notoptions) && isset($notoptions[$key])) {
            unset($notoptions[$key]);
            set_cache('notoptions', $notoptions, 'options');
        }

        if (!app_installing()) {
            $alloptions = $this->loadOptions();
            if (isset($alloptions[$key])) {
                $alloptions[$key] = $serialized_value;
                set_cache('alloptions', $alloptions, 'options');
            }
            else {
                set_cache($key, $serialized_value, 'options');
            }
        }

        return true;
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
    public function delete(string $key)
    {
        $key = trim($key);
        if (empty($key)) {
            return false;
        }

        $this->protectOption($key);

        // Kimlik al, eğer kimlik yoksa geri dön
        $row = $this->newQuery()->select('autoload')->where('key', $key)->first();
        if ($row === null) {
            return false;
        }

        $result = $this->newQuery()->where('key', $key)->delete();

        if (!app_installing()) {
            if ($row->autoload == 1) {
                $alloptions = $this->loadOptions();
                if (is_array($alloptions) && isset($alloptions[$key])) {
                    unset($alloptions[$key]);
                    set_cache('alloptions', $alloptions, 'options');
                }
            }
            else {
                delete_cache($key, 'options');
            }
        }
        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * Candtry'nin özel seçenekleri düzenlenmesi izin vermeyin.
     *
     * `$key` korumalı listede ise uygulamayı sonlandır. Korunan seçenekler 'alloptions' ve 'notoptions' seçenekleridir.
     *
     * @since 1.0
     *
     * @param string $key   Seçenek adı.
     *
     * @return void
     */
    public function protectOption($key): void
    {
        if ($key === 'alloptions' || $key === 'notoptions') {
            die("`$key` adlı seçenek, Candtry'nin korumalı seçeneğidir ve düzenlenemez.");
        }
    }

    public function sanitize($option, $value)
    {
        switch ($option) {
            case 'maintenance':
            case 'address_map_enabled':
            case 'post_author_enabled':
            case 'post_date_enabled':
            case 'sp_author_box_enabled':
            case 'sp_social_box_enabled':
            case 'sp_post_date_enabled':
            case 'sp_related_posts_enabled':
            case 'sp_neighbour_posts_enabled':
            case '':
                $value = (bool) $value;
                break;

            case 'smtp_port':
            case 'posts_per_page':
            case 'post_content_limitt':
            case 'sp_related_posts_quantity':
                $value = (int) $value;

                if (empty($value)) {
                    $value = 1;
                }
                if ($value < -1) {
                    $value = abs($value);
                }
                break;
        }

        return $value;
    }

    /**
     * Varsa veya tüm seçenekleri varsa otomatik olarak yüklenen tüm seçenekleri yükler ve önbelleğe alır.
     *
     * @since 1.0
     *
     * @return array Seçenek listesi.
     */
    public function loadOptions()
    {
        if (!app_installing()) {
            $alloptions = get_cache('alloptions', 'options');
        }
        else {
            $alloptions = false;
        }

        if (!$alloptions) {
            try {
                $options = $this->newQuery()->select(['key', 'value'])->where('autoload', 1)->get();

                if (!$options) {
                    $options = $this->newQuery()->select(['key', 'value'])->get();
                }
            }
            catch (\Illuminate\Database\QueryException $e) {
                $options = [];
            }

            $alloptions = [];

            foreach ($options as $option) {
                $alloptions[$option->key] = $option->value;
            }

            if (!app_installing()) {
                set_cache('alloptions', $alloptions, 'options');
            }
        }

        return $alloptions;
    }

    /**
     * Yeni bir sorgu oluşturucu örneği oluşturun.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newQuery()
    {
        return $this->connection->table($this->table);
    }
}
