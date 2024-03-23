<?php

namespace BenjaminTemitope\Comments\Traits;


use Illuminate\Database\Eloquent\Model;
use BenjaminTemitope\Comments\Contracts\Commentator;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasComments
{
    /**
     * Return all comments for this model.
     *
     * @return MorphMany
     */
    public function comments()
    {
        return $this->morphMany(config('comments.comment_class'), 'commentable')->where('parent_id', null);
    }

    public function getCommentsWithChildrenAttribute(){
        return $this->comments()->with('children')->get();
    }

    public function getCommentsWithRepliesAttribute(){
        return $this->commentsWithChildren;
    }

    /**
     * Attach a comment to this model.
     *
     * @param string $comment
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function comment(string $comment, Model|int|null $parent = null)
    {
        return $this->commentAsUser(auth()->user(), $comment, $parent);
    }

    /**
     * Attach a comment to this model as a specific user.
     *
     * @param Model|null $user
     * @param string $comment
     * @param Model|int|null $parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function commentAsUser(?Model $user, string $comment, Model|int|null $parent = null)
    {
        $commentClass = config('comments.comment_class');

        $comment = new $commentClass([
            'comment' => $comment,
            'is_approved' => ($user instanceof Commentator) ? ! $user->needsCommentApproval($this) : false,
            'user_id' => is_null($user) ? null : $user->getKey(),
            'parent_id' => is_null($parent) ? null : (($parent instanceof $commentClass) ? $parent->getKey() : $parent),
            'commentable_id' => $this->getKey(),
            'commentable_type' => get_class(),
        ]);

        return $this->comments()->save($comment);
    }

}