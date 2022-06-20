<?php

namespace App\Http\Livewire\Item;

use App\Models\Item;
use Filament\Forms\Components\Tabs;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use App\View\Components\MarkdownEditor;
use Filament\Forms\Concerns\InteractsWithForms;

class Comments extends Component implements HasForms
{
    use InteractsWithForms;

    public Item $item;
    public $comments;
    public $content;
    public $private_content;
    public $reply;

    public function mount()
    {
        $this->form->fill();
    }

    public function submit()
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }

        $formState = array_merge($this->form->getState(), [
            'parent_id' => $this->reply,
            'user_id' => auth()->id(),
        ]);

        if (filled($this->private_content)) {
            $formState['content'] = $this->private_content;
            $formState['private'] = true;
        }

        $this->item->comments()->create($formState);

        $this->content = '';
        $this->private_content = '';
        $this->reply = null;

        if ($this->item->project) {
            $this->redirectRoute('projects.items.show', [$this->item->project, $this->item]);
        }

        $this->redirectRoute('items.show', [$this->item]);
    }

    protected function getFormSchema(): array
    {
        if (auth()->user()?->hasAdminAccess()) {
            $reply = $this->item->comments()->find($this->reply);

            return [
                Tabs::make('')->tabs([
                    Tabs\Tab::make(trans('comments.comment'))->schema([
                        MarkdownEditor::make('content')
                                      ->label(trans('comments.comment'))
                                      ->helperText(trans('comments.mention-helper-text'))
                                      ->minLength(3)
                                      ->rules(['required_if:private_content,null,""', 'prohibited_unless:private_content,null,""']),
                    ])
                            ->hidden($reply?->private ?? false)
                            ->id("public-{$this->reply}"),

                    Tabs\Tab::make(trans('comments.private-note'))->schema([
                        MarkdownEditor::make('private_content')
                                      ->label(trans('comments.private-note'))
                                      ->helperText(trans('comments.mention-helper-text'))
                                      ->minLength(3)
                                      ->visible(auth()->check() && auth()->user()->hasAdminAccess())
                                      ->rules(['required_if:content,null,""', 'prohibited_unless:content,null,""']),
                    ])->withAttributes(['class' => 'bg-yellow-50 rounded-xl'])->id("private-{$this->reply}"),
                ]),
            ];
        }

        return [
            MarkdownEditor::make('content')
                          ->label(trans('comments.comment'))
                          ->helperText(trans('comments.mention-helper-text'))
                          ->minLength(3)
                          ->required(),
        ];
    }

    public function render()
    {
        $this->comments = $this->item
            ->comments()
            ->with('user:id,name,email')
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, id')
            ->when(!auth()->user()?->hasAdminAccess(), fn ($query) => $query->where('private', false))
            ->get()
            ->mapToGroups(function ($comment) {
                return [(int)$comment->parent_id => $comment];
            });

        return view('livewire.item.comments');
    }

    public function reply(?int $id = null)
    {
        $this->reply = $id;
    }
}
