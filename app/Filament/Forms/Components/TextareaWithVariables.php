<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Js;

/**
 * A textarea that suggests the variables the text can use: typing "{"
 * opens the list under the field, narrowed as the name is typed. Arrows and
 * Enter (or Tab), or a click, put the chosen one in.
 */
class TextareaWithVariables extends Textarea
{
    /** @var array<string, string>|Closure */
    protected array|Closure $variables = [];

    /**
     * @param  array<string, string>|Closure  $variables  "{variable}" => what it turns into
     */
    public function variables(array|Closure $variables): static
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getVariables(): array
    {
        return $this->evaluate($this->variables);
    }

    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        if ($this->isDisabled() || $this->isReadOnly() || $this->getVariables() === []) {
            return $html;
        }

        $options = collect($this->getVariables())
            ->map(fn (string $description, string $value): array => ['value' => $value, 'description' => $description])
            ->values();

        ob_start(); ?>

        <div
            class="wl-vars"
            x-data="{
                options: <?= e(Js::from($options)) ?>,
                open: false,
                query: '',
                start: 0,
                active: 0,
                position: '',
                get filtered() {
                    return this.options.filter((option) => option.value.slice(1).startsWith(this.query))
                },
                textarea() {
                    return this.$root.querySelector('textarea')
                },
                update() {
                    const textarea = this.textarea()
                    const typed = textarea.value.slice(0, textarea.selectionStart).match(/\{([a-z_]*)$/)

                    if (! typed) {
                        this.open = false

                        return
                    }

                    this.query = typed[1]
                    this.start = textarea.selectionStart - typed[0].length
                    this.active = 0
                    this.open = this.filtered.length > 0

                    // Right under the line being typed, not under the whole field.
                    const field = textarea.getBoundingClientRect()
                    const root = this.$root.getBoundingClientRect()
                    const caret = this.caret(textarea)
                    const width = Math.min(320, field.width)
                    const left = Math.min(caret.left, field.width - width)

                    this.position = `top: ${field.top - root.top + caret.top + 4}px; left: ${field.left - root.left + left}px; width: ${width}px`
                },
                caret(textarea) {
                    const style = window.getComputedStyle(textarea)
                    const mirror = document.createElement('div')
                    const marker = document.createElement('span')

                    for (const property of ['boxSizing', 'width', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'tabSize']) {
                        mirror.style[property] = style[property]
                    }

                    Object.assign(mirror.style, { position: 'absolute', visibility: 'hidden', top: '0', left: '-9999px', whiteSpace: 'pre-wrap', overflowWrap: 'break-word', borderStyle: 'solid' })
                    mirror.textContent = textarea.value.slice(0, textarea.selectionStart)
                    marker.textContent = '\u200b'
                    mirror.appendChild(marker)
                    document.body.appendChild(mirror)

                    const position = {
                        top: marker.offsetTop + marker.offsetHeight - textarea.scrollTop,
                        left: marker.offsetLeft,
                    }

                    mirror.remove()

                    return position
                },
                keydown(event) {
                    if (! this.open) {
                        return
                    }

                    const count = this.filtered.length

                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault()
                        this.active = (this.active + (event.key === 'ArrowDown' ? 1 : count - 1)) % count
                    } else if (event.key === 'Enter' || event.key === 'Tab') {
                        event.preventDefault()
                        this.choose(this.filtered[this.active])
                    } else if (event.key === 'Escape') {
                        event.preventDefault()
                        event.stopPropagation()
                        this.open = false
                    }
                },
                choose(option) {
                    const textarea = this.textarea()
                    const caret = this.start + option.value.length

                    textarea.value = textarea.value.slice(0, this.start) + option.value + textarea.value.slice(textarea.selectionStart)
                    textarea.setSelectionRange(caret, caret)
                    textarea.focus()
                    textarea.dispatchEvent(new Event('input', { bubbles: true }))
                    this.open = false
                },
            }"
            x-on:input="update()"
            x-on:click="update()"
            x-on:keydown="keydown($event)"
            x-on:click.outside="open = false"
        >
            <?= $html ?>

            <div
                wire:ignore
                class="wl-vars-menu"
                role="listbox"
                x-show="open"
                x-cloak
                x-bind:style="position"
            >
                <div class="wl-vars-title">Variáveis</div>
                <template x-for="(option, index) in filtered" x-bind:key="option.value">
                    <div
                        role="option"
                        class="wl-vars-option"
                        x-bind:class="{ 'wl-vars-active': index === active }"
                        x-bind:aria-selected="index === active"
                        x-on:mouseenter="active = index"
                        x-on:mousedown.prevent="choose(option)"
                    >
                        <code x-text="option.value"></code>
                        <span x-text="option.description"></span>
                    </div>
                </template>
            </div>
        </div>

        <style>
            .wl-vars { position: relative; }
            .wl-vars-menu {
                position: absolute;
                z-index: 30;
                max-width: 32rem;
                max-height: 18rem;
                overflow-y: auto;
                padding: 0.25rem;
                border-radius: 0.5rem;
                background: #fff;
                border: 1px solid var(--gray-200);
                box-shadow: 0 10px 20px -5px rgb(0 0 0 / 0.15);
            }
            .wl-vars-title {
                padding: 0.375rem 0.625rem 0.25rem;
                font-size: 0.6875rem;
                font-weight: 600;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                color: var(--gray-500);
            }
            .wl-vars-option {
                display: flex;
                flex-direction: column;
                gap: 0.125rem;
                padding: 0.4375rem 0.625rem;
                border-radius: 0.375rem;
                cursor: pointer;
                font-size: 0.8125rem;
                color: var(--gray-600);
            }
            .wl-vars-option code {
                font-size: 0.8125rem;
                font-weight: 600;
                color: var(--primary-600);
            }
            .wl-vars-active { background: var(--gray-100); }
            .dark .wl-vars-menu { background: var(--gray-900); border-color: rgb(255 255 255 / 0.1); }
            .dark .wl-vars-option { color: var(--gray-400); }
            .dark .wl-vars-option code { color: var(--primary-400); }
            .dark .wl-vars-active { background: rgb(255 255 255 / 0.05); }
        </style>

        <?php return (string) ob_get_clean();
    }
}
