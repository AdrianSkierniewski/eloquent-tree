<?php namespace Gzero\EloquentTree\Model;


class Tree extends \Illuminate\Database\Eloquent\Model {

    /**
     * Parent object
     *
     * @var static
     */
    protected $_parent;
    /**
     * Array for children elements
     *
     * @var array
     */
    protected $_children = array();
    /**
     * Database mapping tree fields
     *
     * @var Array
     */
    protected static $_tree_cols = array(
        'path'   => 'path',
        'parent' => 'parent_id',
        'level'  => 'level'
    );

    /**
     * ONLY FOR TESTS!
     * Metod resets static::$booted
     */
    public static function __resetBootedStaticProperty()
    {
        static::$booted = array();
    }

    /**
     * Get tree column for actual model
     *
     * @param string $name column name [path|parent|level]
     *
     * @return null
     */
    public function getTreeColumn($name)
    {
        if (!empty(static::$_tree_cols[$name])) {
            return static::$_tree_cols[$name];
        }
        return NULL;
    }

    protected static function boot()
    {
        parent::boot();
        static::observe(new Observer());
    }

    /**
     * Set node as root node
     *
     * @return $this
     */
    public function setAsRoot()
    {
        $this->_handleNewNodes();
        $this->{$this->getTreeColumn('path')}   = $this->{$this->getKeyName()} . '/';
        $this->{$this->getTreeColumn('parent')} = NULL;
        $this->{$this->getTreeColumn('level')}  = 0;
        $this->save();
        return $this;
    }

    /**
     * Set node as child of $parent node
     *
     * @param Tree $parent
     *
     * @return $this
     */
    public function setChildOf(Tree $parent)
    {
        $this->_handleNewNodes();
        $this->{$this->getTreeColumn('path')}   = $parent->{$this->getTreeColumn('path')} . $this->{$this->getKeyName()} . '/';
        $this->{$this->getTreeColumn('parent')} = $parent->{$this->getKeyName()};
        $this->{$this->getTreeColumn('level')}  = $parent->{$this->getTreeColumn('level')} + 1;
        $this->save();
        return $this;
    }

    /**
     * Set node as sibling of $sibling node
     *
     * @param Tree $sibling
     *
     * @return $this
     */
    public function setSiblingOf(Tree $sibling)
    {
        $this->_handleNewNodes();
        $this->{$this->getTreeColumn('path')}   =
            preg_replace('/\d\/$/', '', $sibling->{$this->getTreeColumn('path')}) . $this->{$this->getKeyName()} . '/';
        $this->{$this->getTreeColumn('parent')} = $sibling->{$this->getTreeColumn('parent')};
        $this->{$this->getTreeColumn('level')}  = $sibling->{$this->getTreeColumn('level')};
        $this->save();
        return $this;
    }

    /**
     * Check if node is root
     *
     * @return bool
     */
    public function isRoot()
    {
        return (empty($this->{$this->getTreeColumn('parent')})) ? TRUE : FALSE;
    }

    /**
     * @return static
     */
    public function getParent()
    {
        if ($this->{$this->getTreeColumn('parent')}) {
            if (!$this->_parent) {
                return $this->_parent = static::where($this->getKeyName(), '=', $this->{$this->getTreeColumn('parent')})
                    ->first();
            }
            return $this->_parent;
        }
        return NULL;
    }


//
//    /**
//     * Funkcja zwraca zbudowane zapytanie, który wciągnie dzieci danego obiektu
//     *
//     * @param String $lang_code Kod języka w którym wyciągamy
//     *
//     * @return Laravel\Database\Eloquent\Query Jeszcze nie wykonany obiekt zapytania
//     */
    public function getChildren()
    {
        return static::where($this->getTreeColumn('parent'), '=', $this->{$this->getKeyName()});
    }

//
//    /**
//     * Funkcja wyciąga wszystkich potomków danego węzła
//     *
//     * @param String $lang_code Kod języka w którym wyciągamy
//     *
//     * @return Laravel\Database\Eloquent\Query Jeszcze nie wykonany obiekt zapytania
//     */
//    public function getDescendants($lang_code)
//    {
//        return static::withLangs($lang_code)
//            ->where(
//                static::$_tree_cols['path'],
//                'LIKE',
//                $this->{static::$_tree_cols['path']} . '%'
//            )
//            ->where(static::$table . '.' . static::$key, '!=', $this->{static::$key}) // Bez węzła dla którego szukamy potomków
//            ->order_by(static::$_tree_cols['level'], 'ASC'); // ?
//    }
//
//    /**
//     * Funkcja wyciąga wszystkich przodków danego węzła
//     *
//     * @param String $lang_code Kod języka w którym wyciągamy
//     *
//     * @return Laravel\Database\Eloquent\Query Jeszcze nie wykonany obiekt zapytania
//     */
//    public function getAncestors($lang_code)
//    {
//        return static::withLangs($lang_code)
//            ->where(
//                static::$_tree_cols['path'],
//                'LIKE',
//                '%' . $this->{static::$key} . '/'
//            )
//            ->where(static::$table . '.' . static::$key, '!=', $this->{static::$key}) // Bez węzła dla którego szukamy przodków
//            ->order_by(static::$_tree_cols['level'], 'ASC');
//    }
//
//

//
//    /**
//     * Funkcja zwraca wszystkie elementy danego drzewa posortowane po wadze. Ze zbioru takich elementów można stworzyć drzewo
//     * za pomocą metody
//     *
//     * @param Int    $root_id   Id węzła dla którego chcemy zbudować drzewo
//     * @param String $lang_code Kod języka
//     *
//     * @return mixed
//     */
//    public static function fetchTree($root_id, $lang_code)
//    {
//        return static::withLangs($lang_code)
//            ->where( // Root
//                static::$_tree_cols['path'],
//                'LIKE',
//                "$root_id/%"
//            )
//            ->order_by(static::$_tree_cols['level'], 'ASC');
//    }
//
//    //-----------------------------------------------------------------------------------------------
//    // START                         PROTECTED/PRIVATE
//    //-----------------------------------------------------------------------------------------------

    protected function _handleNewNodes()
    {
        if (!$this->exists) {
            $this->save();
        }
    }
//    /**
//     * Funkcja odtwarza z rekordów z bazy strukturę drzewa po stronie PHP
//     *
//     * @param array  $records   Lista rekordów z bazy przedstawiających drzewo
//     * @param string $presenter Opcjonalna nazwa prezentera, który będzie zwracany w wynikach
//     *
//     * @return static
//     * @throws Exception
//     */
//    public static function buildTree(array $records, $presenter = '')
//    {
//        $count = 0;
//        $refs  = array(); // Tablica referencji do przechowywania rekordów podczas budowy drzewa
//        foreach ($records as &$record) {
//            $refs[$record->{static::$key}] = & $record; // Dodajemy do tablicy referencji nasz rekord i identyfikujemy go po id
//            if ($count === 0) { // Stosujemy taki zapis ponieważ uwzględniamy też budowanie poddrzew, rootem jest zawsze 1 węzeł
//                $root = & $record;
//                $count++;
//            } else {
//                if (!empty($presenter)) { // Nie jest to root,  więc dodajemy do rodzica
//                    if (class_exists($presenter)) {
//                        $refs[$record->{static::$_tree_cols['parent']}]->children[] = new $presenter($record);
//                    } else {
//                        throw new Exception("No presenter class found: $presenter");
//                    }
//                } else {
//                    $refs[$record->{static::$_tree_cols['parent']}]->children[] = $record;
//                }
//            }
//        }
//        return (!isset($root)) ? FALSE : $root;
//    }
//
//
//    /**
//     * Rekurencja uaktualniająca poziom dzieci
//     *
//     * @param Tree_Base $parent
//     */
//    protected function _updateChildren(Tree_Base $parent)
//    {
//        foreach ($parent->getChildren()->get() as $child) {
//            $child->{static::$_tree_cols['level']} = $parent->{static::$_tree_cols['level']} + 1;
//            $child->{static::$_tree_cols['path']}  = $parent->{static::$_tree_cols['path']} . $child->{static::$key} . '/';
//            $child->save();
//            $this->_updateChildren($child);
//        }
//    }
//
//    /**
//     * Funkcja buduje zapytanie wyciągające wszystkie korzenie
//     *
//     * @return Laravel\Database\Eloquent\Query Jeszcze nie wykonany obiekt zapytania
//     */
//    protected static function _getRoots()
//    {
//        return static::where(static::$_tree_cols['parent'], 'IS', DB::raw('NULL'));
//    }
//
//    /**
//     * Funkcja buduje zapytanie wyciągające root-a dla danego drzewa
//     *
//     * @param String $path_or_id Ścieżka dla której szukamy root`a
//     *
//     * @return Laravel\Database\Eloquent\Query Jeszcze nie wykonany obiekt zapytania
//     */
//    protected static function _getRoot($path_or_id)
//    {
//        if (is_numeric($path_or_id)) { // Jeśli mamy do czynienia z menu_id - czyli menu_link_id root`a
//            $id = $path_or_id;
//        } else {
//            $id = preg_replace('/\/.+/', '', $path_or_id, 1);
//        }
//        return static::_getRoots()->where(static::$table . '.' . static::$key, '=', $id);
//    }
//
//    /**
//     * Funkcja podejmuje decyzje jak powinna wyglądać ścieżka w zależności od tego czy węzeł jest już zapisany w bazie
//     *
//     * @param String $parent_path Ścieżka rodzica
//     */
//    private function __pathExistSetter($parent_path)
//    {
//        if ($this->exists) { // Gdy węzeł był już dodany
//            $this->{static::$_tree_cols['path']} = $parent_path . $this->get_key() . '/';
//        } else { // Jeśli nie było węzła to na początku dodajemy path rodzica, a dopiero save() ustawia poprawny path
//            $this->{static::$_tree_cols['path']} = $parent_path; // Bez id rodzeństwa
//        }
//    }
}
