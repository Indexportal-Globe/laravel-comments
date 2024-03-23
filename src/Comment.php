<?php

namespace BenjaminTemitope\Comments;

use App\Models\Filters\CommentStatusSelectFilter;
use BenjaminTemitope\Comments\Contracts\Commentator;
use BenjaminTemitope\Comments\Traits\HasComments;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Lacodix\LaravelModelFilter\Traits\HasFilters;

class Comment extends Model
{
    use HasComments, HasFilters;

    protected $fillable = [
        'comment',
        'user_id',
        'parent_id',
        'is_approved'
    ];

    protected $casts = [
        'is_approved' => 'boolean'
    ];

    // ONLY FOR THIS PROJECT
    
    protected array $filters = [
        CommentStatusSelectFilter::class
    ];

    /**
     * Generate a unique indentifier for Blade view.
     */
    public function uniqueIndentifier() :string {
        return $this->id . strtotime($this->created_at);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function commentator()
    {
        return $this->belongsTo($this->getAuthModelName(), 'user_id');
    }

    public function parent() {
        return $this->belongsTo(config('comments.comment_class'), 'parent_id');
    }

    public function children() {
        return $this->hasMany(config('comments.comment_class'), 'parent_id');
    }

    public function getRepliesAttribute(){
        return $this->children;
    }

    public function approve()
    {
        $this->update([
            'is_approved' => true,
        ]);

        return $this;
    }
  
    public function disapprove()
    {
        $this->update([
            'is_approved' => false,
        ]);

        return $this;
    }

    public function reply(string $comment){
        return $this->replyAsUser(auth()->user(), $comment);
    }

    public function replyAsUser(?Model $user, string $comment){
        $commentClass = config('comments.comment_class');

        $reply = new $commentClass([
            'comment' => $comment,
            'is_approved' => ($user instanceof Commentator) ? ! $user->needsCommentApproval($this) : false,
            'user_id' => is_null($user) ? null : $user->getKey(),
            'parent_id' => $this->value('id'),
            'commentable_id' => $this->value('commentable_id'),
            'commentable_type' => $this->value('commentable_type')
        ]);

        $reply->save();

        return $this->children()->save($reply);
    }

    public function hasReplies() :bool {
        return ($this->children()->count());
    }

    protected function getAuthModelName()
    {
        if (config('comments.user_model')) {
            return config('comments.user_model');
        }

        if (!is_null(config('auth.providers.users.model'))) {
            return config('auth.providers.users.model');
        }

        throw new Exception('Could not determine the commentator model name.');
    }
}
