<?php
require_once __DIR__ . '/TemplateState.php';

/**
 * Класс-сущность шаблона таблицы.
 * Хранит данные одной записи из cit_schema.table_templates и инкапсулирует
 * состояние шаблона (активен/неактивен/отсутствует).
 */
class Template
{
    private int $id;
    private string $name;
    private array $headers;
    private array $structure;
    private bool $active;
    private TemplateState $state;

    /**
     * Создаёт объект шаблона на основе данных из базы
     * Внутри сразу выбирает, какое состояние установить активное или неактивное.
     * @param int    $id        ID шаблона (template_id).
     * @param string $name      Название шаблона (template_name).
     * @param array  $headers   Заголовки колонок (template_headers).
     * @param array  $structure Структура таблицы (template_structure).
     * @param bool   $active    Признак активности (is_active).
     */
    public function __construct(
        int $id,
        string $name,
        array $headers,
        array $structure,
        bool $active
    ) {
        $this->id        = $id;
        $this->name      = $name;
        $this->headers   = $headers;
        $this->structure = $structure;
        $this->active    = $active;

        // Выбираем состояние активен или неактивен
        $this->state = $active
            ? new ActiveTemplateState()
            : new InactiveTemplateState();
    }

    /**
     * Создаёт специальный объект на случай,
     * когда активного шаблона нет вообще.
     * Используется, чтобы код мог работать с Template,
     * даже если в БД ничего не найдено.
     * @return Template Пустой шаблон со state = NoTemplateState.
     */
    public static function createEmpty(): Template
    {
        $tpl = new self(0, 'Нет активного шаблона', [], ['rows' => []], false);
        $tpl->state = new NoTemplateState();
        return $tpl;
    }

    /**
     * Возвращает ID шаблона.
     *
     * @return int ID шаблона.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Возвращает название шаблона.
     *
     * @return string Название шаблона.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает массив заголовков колонок
     *  @return array Заголовки колонок.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Возвращает структуру шаблона (строки, ячейки и т.д.)
     * @return array Структура шаблона.
     */
    public function getStructure(): array
    {
        return $this->structure;
    }

    /**
     * Возвращает признак активности шаблона (is_active).
     *
     * @return bool true если шаблон активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /** Возвращает объект текущего состояния шаблона(паттерн State) 
     *  @return TemplateState Текущее состояние.
    */
    public function getState(): TemplateState
    {
        return $this->state;
    }

    /**
     * Показывает, можно ли использовать шаблон для заполнения пользователями МО
     * @return bool true если шаблон разрешено использовать для заполнения.
     */
    public function canBeUsedForFill(): bool
    {
        return $this->state->canBeUsedForFill();
    }
}