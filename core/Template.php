<?php
require_once __DIR__ . '/TemplateState.php';

/**
 * Класс-сущность шаблона таблицы.
 * Хранит данные одной записи из cit_schema.table_templates.
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
     */
    public static function createEmpty(): Template
    {
        $tpl = new self(0, 'Нет активного шаблона', [], ['rows' => []], false);
        $tpl->state = new NoTemplateState();
        return $tpl;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает массив заголовков колонок
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Возвращает структуру шаблона (строки, ячейки и т.д.)
     */
    public function getStructure(): array
    {
        return $this->structure;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** Возвращает объект текущего состояния шаблона. */
    public function getState(): TemplateState
    {
        return $this->state;
    }

    /**
     * Показывает, можно ли использовать шаблон для заполнения пользователями МО
     */
    public function canBeUsedForFill(): bool
    {
        return $this->state->canBeUsedForFill();
    }
}