<?php
/**
 * Состояния шаблона (паттерн Состояние).
 * Каждое состояние описывает, как вести себя с шаблоном:
 * активен, неактивен, отсутствует.
 */

/**
 * Базовый интерфейс состояния шаблона.
 */
interface TemplateState
{
    /**
     * Описание текущего состояния
     */
    public function describe(): string;

    /**
     * Можно ли использовать этот шаблон для заполнения пользователем
     */
    public function canBeUsedForFill(): bool;

    /**
     * Можно ли сделать шаблон активным
     */
    public function canBeActivated(): bool;
}

/**
 * Состояние активный шаблон
 */
class ActiveTemplateState implements TemplateState
{
    public function describe(): string
    {
        return 'Шаблон активен и используется пользователями';
    }

    public function canBeUsedForFill(): bool
    {
        return true;
    }

    public function canBeActivated(): bool
    {
        return false; // уже активен
    }
}

/**
 * Состояние шаблон существует, но не активен
 */
class InactiveTemplateState implements TemplateState
{
    public function describe(): string
    {
        return 'Шаблон существует, но не активен';
    }

    public function canBeUsedForFill(): bool
    {
        return false;
    }

    public function canBeActivated(): bool
    {
        return true;
    }
}

/**
 * Состояние активного шаблона вообще нет
 */
class NoTemplateState implements TemplateState
{
    public function describe(): string
    {
        return 'Активный шаблон не задан';
    }

    public function canBeUsedForFill(): bool
    {
        return false;
    }

    public function canBeActivated(): bool
    {
        return false;
    }
}