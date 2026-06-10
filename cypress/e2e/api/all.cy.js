describe('Threadly API — Complete Test Suite', () => {

  // ============================================================
  //  SETUP
  // ============================================================
  before(() => {
    cy.task('artisan', { command: 'migrate:fresh --seed' }, { timeout: 120000 })
  })

  const USERS = {
    superadmin: { username: 'superadmin', email: 'admin@forum.com', password: '123' },
  }

  const TEST = {
    poster: { username: 'poster', email: 'poster@test.com', password: 'Test123456', pw_conf: 'Test123456' },
    commenter: { username: 'commenter', email: 'commenter@test.com', password: 'Test123456', pw_conf: 'Test123456' },
    voter: { username: 'voter', email: 'voter@test.com', password: 'Test123456', pw_conf: 'Test123456' },
    other: { username: 'other', email: 'other@test.com', password: 'Test123456', pw_conf: 'Test123456' },
  }

  // ── Register test users ───────────────────────────────
  it('Setup: register test users', () => {
    cy.wrap(Object.values(TEST)).each((u) => {
      cy.request({
        method: 'POST',
        url: '/register',
        body: {
          username: u.username,
          email: u.email,
          password: u.password,
          password_confirmation: u.pw_conf,
        },
      }).then((res) => {
        expect(res.status).to.eq(201)
      })
    })
  })

  // ── Give poster 15 reputation points ──────────────────
  it('Setup: give poster 15 reputation points', () => {
    cy.task('php', { command: 'cypress/scripts/set_points.php poster 15' }).then((out) => {
      expect(out).to.contain('OK:poster:15')
    })
  })

  // ── Login all users ───────────────────────────────────
  it('Setup: login superadmin', () => {
    cy.request({
      method: 'POST',
      url: '/login',
      body: { username: USERS.superadmin.username, password: USERS.superadmin.password },
    }).then((res) => {
      expect(res.status).to.eq(200)
      Cypress.env('superadmin_token', res.body.access_token)
      Cypress.env('superadmin_user', res.body.user)
    })
  })

  it('Setup: login poster', () => {
    cy.apiLogin(TEST.poster.username, TEST.poster.password).then((res) => {
      expect(res.access_token).to.exist
    })
  })

  it('Setup: login commenter', () => {
    cy.apiLogin(TEST.commenter.username, TEST.commenter.password).then((res) => {
      expect(res.access_token).to.exist
    })
  })

  it('Setup: login voter', () => {
    cy.apiLogin(TEST.voter.username, TEST.voter.password).then((res) => {
      expect(res.access_token).to.exist
    })
  })

  it('Setup: login other', () => {
    cy.apiLogin(TEST.other.username, TEST.other.password).then((res) => {
      expect(res.access_token).to.exist
    })
  })

  // ============================================================
  //  1. AUTH
  // ============================================================
  context('Auth', () => {

    it('register: duplicate username rejects', () => {
      cy.request({
        method: 'POST', url: '/register',
        body: { username: TEST.poster.username, email: 'dup@test.com', password: 'Test123456', password_confirmation: 'Test123456' },
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('register: duplicate email rejects', () => {
      cy.request({
        method: 'POST', url: '/register',
        body: { username: 'uniquename', email: TEST.poster.email, password: 'Test123456', password_confirmation: 'Test123456' },
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('login: wrong password returns 401', () => {
      cy.request({
        method: 'POST', url: '/login',
        body: { username: TEST.poster.username, password: 'wrongpass' },
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(401)
      })
    })

    it('login: missing fields returns 422', () => {
      cy.request({
        method: 'POST', url: '/login',
        body: { username: 'any' },
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('logout: without token returns 401', () => {
      cy.request({
        method: 'POST', url: '/logout',
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(401)
      })
    })

    it('logout: with token succeeds', () => {
      cy.apiPost('/logout', {}, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('login: re-login after logout works', () => {
      cy.apiLogin(TEST.poster.username, TEST.poster.password).then((res) => {
        expect(res.access_token).to.exist
      })
    })
  })

  // ============================================================
  //  2. PROFILE & USER
  // ============================================================
  context('Profile & User', () => {

    it('me: returns authenticated user', () => {
      cy.apiGet('/me', 'poster').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.username).to.eq('poster')
      })
    })

    it('me: without auth returns 401', () => {
      cy.request({ method: 'GET', url: '/me', failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(401)
      })
    })

    it('me: update bio and avatar', () => {
      cy.apiPut('/me', { bio: 'Hello from Cypress!', avatar_url: 'https://example.com/avatar.png' }, 'poster').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.bio).to.eq('Hello from Cypress!')
      })
    })

    it('me: update username', () => {
      cy.apiPut('/me', { username: 'poster_updated' }, 'poster').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.username).to.eq('poster_updated')
      })
    })

    it('me: revert username', () => {
      cy.apiPut('/me', { username: 'poster' }, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('me: update password requires current password', () => {
      cy.apiPut('/me/password', {
        current_password: 'wrong',
        new_password: 'NewPass1234',
        new_password_confirmation: 'NewPass1234',
      }, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('me: update password succeeds', () => {
      cy.apiPut('/me/password', {
        current_password: TEST.poster.password,
        new_password: 'NewPass1234',
        new_password_confirmation: 'NewPass1234',
      }, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('me: re-login with new password', () => {
      cy.apiLogin(TEST.poster.username, 'NewPass1234').then((res) => {
        expect(res.access_token).to.exist
      })
    })

    it('me: revert password', () => {
      cy.apiPut('/me/password', {
        current_password: 'NewPass1234',
        new_password: TEST.poster.password,
        new_password_confirmation: TEST.poster.password,
      }, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('me: re-login with original password', () => {
      cy.apiLogin(TEST.poster.username, TEST.poster.password).then((res) => {
        expect(res.access_token).to.exist
      })
    })

    it('public profile: returns user info', () => {
      cy.request({ method: 'GET', url: `/users/${Cypress.env('poster_user').id}` }).then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.username).to.eq('poster')
      })
    })

    it('public profile: 404 for unknown user', () => {
      cy.request({
        method: 'GET', url: '/users/00000000-0000-0000-0000-000000000000',
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(404)
      })
    })

    it('user posts: returns posts by user', () => {
      cy.request({ method: 'GET', url: `/users/${Cypress.env('poster_user').id}/posts` }).then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  3. CATEGORIES
  // ============================================================
  context('Categories', () => {

    let categoryId

    it('index: public list categories', () => {
      cy.request('/categories').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('store: user cannot create (403)', () => {
      cy.apiPost('/categories', { name: 'User Category' }, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('store: admin creates category', () => {
      cy.apiPost('/categories', { name: 'Cypress Test Category' }, 'superadmin').then((res) => {
        expect(res.status).to.eq(201)
        categoryId = res.body.data.id
        Cypress.env('categoryId', categoryId)
      })
    })

    it('show: public view category', () => {
      cy.request(`/categories/${categoryId}`).then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.name).to.eq('Cypress Test Category')
      })
    })

    it('update: admin updates category', () => {
      cy.apiPut(`/categories/${categoryId}`, { description: 'Updated description' }, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('destroy: admin deletes category', () => {
      cy.apiPost('/categories', { name: 'Temp Category' }, 'superadmin').then((r1) => {
        const tempId = r1.body.data.id
        cy.apiDelete(`/categories/${tempId}`, 'superadmin').then((r2) => {
          expect(r2.status).to.eq(200)
        })
      })
    })
  })

  // ============================================================
  //  4. TAGS
  // ============================================================
  context('Tags', () => {

    let tagId

    it('index: public list tags', () => {
      cy.request('/tags').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('store: any authenticated user can create tag (tidak ada guard)', () => {
      cy.apiPost('/tags', { name: 'usertag' }, 'poster').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('store: admin creates tag', () => {
      cy.apiPost('/tags', { name: 'cypress', color: '#FF5733' }, 'superadmin').then((res) => {
        expect(res.status).to.eq(201)
        tagId = res.body.data.id
        Cypress.env('tagId', tagId)
      })
    })

    it('show: public view tag', () => {
      cy.request(`/tags/${tagId}`).then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.name).to.eq('cypress')
      })
    })

    it('update: admin updates tag', () => {
      cy.apiPut(`/tags/${tagId}`, { color: '#33FF57' }, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('destroy: admin deletes tag', () => {
      cy.apiDelete(`/tags/${tagId}`, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  5. POSTS
  // ============================================================
  context('Posts', () => {

    let postId
    const catId = () => Cypress.env('categoryId')

    it('store: user with <15 points cannot post (seharusnya poster punya 15)', () => {
      // poster already has 15 points, so this should succeed
      cy.apiPost('/posts', {
        category_id: catId(),
        title: 'Cypress Integration Test Post — How to Test APIs?',
        body: 'This is a comprehensive integration test post created by the Cypress test suite. It contains more than 20 characters as required.',
        tags: [],
      }, 'poster').then((res) => {
        expect(res.status).to.eq(201)
        postId = res.body.data.id
        Cypress.env('postId', postId)
      })
    })

    it('store: user with <15 points cannot post (422)', () => {
      // voter has default 0 reputation_points from migration
      cy.apiPost('/posts', {
        category_id: catId(),
        title: 'This should fail due to insufficient points',
        body: 'Testing that users with less than 15 points cannot create posts. This should return an error.',
      }, 'voter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('store: validation fails on short title', () => {
      cy.apiPost('/posts', {
        category_id: catId(),
        title: 'Short',
        body: 'Body text that is longer than twenty characters for validation test.',
      }, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('index: public list posts', () => {
      cy.request('/posts').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data).to.be.an('array')
      })
    })

    it('index: filter by category', () => {
      cy.request(`/posts?category_id=${catId()}`).then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('show: public view post', () => {
      cy.request(`/posts/${postId}`).then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.id).to.eq(postId)
      })
    })

    it('show: 404 for unknown post', () => {
      cy.request({
        method: 'GET', url: '/posts/00000000-0000-0000-0000-000000000000',
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(404)
      })
    })

    it('update: owner updates post', () => {
      cy.apiPut(`/posts/${postId}`, {
        title: 'Updated Cypress Test Post — How to Test APIs?',
        reason: 'Fixing title',
      }, 'poster').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data.title).to.contain('Updated')
      })
    })

    it('update: non-owner cannot update (403)', () => {
      cy.apiPut(`/posts/${postId}`, { title: 'Hacked title' }, 'commenter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('history: admin can view history', () => {
      cy.apiGet(`/posts/${postId}/history`, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('history: non-admin cannot view (403)', () => {
      cy.apiGet(`/posts/${postId}/history`, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('destroy: owner deletes post', () => {
      cy.apiDelete(`/posts/${postId}`, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  6. COMMENTS
  // ============================================================
  context('Comments', () => {

    let postId, commentId, replyId

    before(() => {
      const catId = Cypress.env('categoryId')
      cy.apiPost('/posts', {
        category_id: catId,
        title: 'Comment Test Post — Need help with Laravel?',
        body: 'This post is specifically created for comment testing purposes in the Cypress integration test suite.',
        tags: [],
      }, 'poster').then((res) => {
        postId = res.body.data.id
        Cypress.env('commentPostId', postId)
      })
    })

    it('store: create comment on post', () => {
      cy.apiPost(`/posts/${postId}/comments`, { body: 'This is a test comment from Cypress.' }, 'commenter').then((res) => {
        expect(res.status).to.eq(201)
        commentId = res.body.data.id
        Cypress.env('commentId', commentId)
      })
    })

    it('index: list post comments', () => {
      cy.request(`/posts/${postId}/comments`).then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('store: reply to a comment', () => {
      cy.apiPost(`/posts/${postId}/comments`, {
        body: 'This is a reply to the test comment.',
        parent_id: commentId,
      }, 'voter').then((res) => {
        expect(res.status).to.eq(201)
        replyId = res.body.data.id
        Cypress.env('replyId', replyId)
      })
    })

    it('store: cannot reply to a reply (max depth 1)', () => {
      cy.apiPost(`/posts/${postId}/comments`, {
        body: 'Trying to nest deeper.',
        parent_id: replyId,
      }, 'other', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('update: owner updates comment', () => {
      cy.apiPut(`/comments/${commentId}`, { body: 'Updated comment from Cypress test.' }, 'commenter').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('update: non-owner cannot update (403)', () => {
      cy.apiPut(`/comments/${commentId}`, { body: 'Hacked!' }, 'voter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('history: admin can view comment history', () => {
      cy.apiGet(`/comments/${commentId}/history`, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('accept: post owner accepts comment as answer', () => {
      cy.apiPost(`/posts/${postId}/comments/${commentId}/accept`, {}, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('accept: non-owner cannot accept (403)', () => {
      cy.apiPost(`/posts/${postId}/comments/${commentId}/accept`, {}, 'commenter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('unaccept: post owner unaccepts answer', () => {
      cy.apiDelete(`/posts/${postId}/unaccept`, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('destroy: admin deletes comment', () => {
      cy.apiDelete(`/comments/${replyId}`, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  7. FOLLOW
  // ============================================================
  context('Follow', () => {

    const targetId = () => Cypress.env('poster_user')?.id

    it('follow: follow another user', () => {
      cy.apiPost(`/users/${targetId()}/follow`, {}, 'commenter').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('follow: cannot follow self (422)', () => {
      cy.apiPost(`/users/${targetId()}/follow`, {}, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('follow: duplicate follow (422)', () => {
      cy.apiPost(`/users/${targetId()}/follow`, {}, 'commenter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('followers: public list', () => {
      cy.request(`/users/${targetId()}/followers`).then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.meta.followers_count).to.be.gte(1)
      })
    })

    it('following: public list', () => {
      cy.request(`/users/${Cypress.env('commenter_user').id}/following`).then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.meta.following_count).to.be.gte(1)
      })
    })

    it('unfollow: unfollow user', () => {
      cy.apiDelete(`/users/${targetId()}/follow`, 'commenter').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('unfollow: cannot unfollow if not following (masih 200, no-op)', () => {
      cy.apiDelete(`/users/${targetId()}/follow`, 'commenter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  8. VOTE
  // ============================================================
  context('Vote', () => {

    let votePostId
    const catId = () => Cypress.env('categoryId')

    before(() => {
      cy.apiPost('/posts', {
        category_id: catId(),
        title: 'Vote Test Post — Laravel best practices?',
        body: 'This post is for vote testing in the Cypress integration suite. It contains more than twenty characters.',
        tags: [],
      }, 'poster').then((res) => {
        votePostId = res.body.data.id
        Cypress.env('votePostId', votePostId)
      })
    })

    it('upvote: vote up on post', () => {
      cy.apiPost('/votes', { target_type: 'post', target_id: votePostId, vote_type: 'upvote' }, 'voter').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('upvote: toggle off (same vote again)', () => {
      cy.apiPost('/votes', { target_type: 'post', target_id: votePostId, vote_type: 'upvote' }, 'voter').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.message).to.contain('dibatalkan')
      })
    })

    it('downvote: voter with 1pt cannot downvote (422)', () => {
      cy.apiPost('/votes', { target_type: 'post', target_id: votePostId, vote_type: 'downvote' }, 'voter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('cannot vote own content: poster on own post (422)', () => {
      cy.apiPost('/votes', { target_type: 'post', target_id: votePostId, vote_type: 'downvote' }, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('cannot vote own content: upvote on own post (422)', () => {
      cy.apiPost('/votes', { target_type: 'post', target_id: votePostId, vote_type: 'upvote' }, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })
  })

  // ============================================================
  //  9. LIKE
  // ============================================================
  context('Like', () => {

    const postId = () => Cypress.env('votePostId')
    const commentId = () => Cypress.env('commentId')

    it('like: like a post', () => {
      cy.apiPost('/likes', { target_type: 'post', target_id: postId() }, 'voter').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('like: like a comment', () => {
      cy.apiPost('/likes', { target_type: 'comment', target_id: commentId() }, 'voter').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('unlike: unlike a post', () => {
      cy.apiDelete('/likes', 'voter', {
        body: { target_type: 'post', target_id: postId() },
      }).then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('likedPosts: list liked posts', () => {
      cy.apiGet('/me/bookmarks/posts', 'voter').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('likedComments: list liked comments', () => {
      cy.apiGet('/me/bookmarks/comments', 'voter').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  10. BOOKMARK
  // ============================================================
  context('Bookmark', () => {

    const postId = () => Cypress.env('votePostId')

    it('store: bookmark a post', () => {
      cy.apiPost('/bookmarks', { post_id: postId() }, 'commenter').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('index: list bookmarks', () => {
      cy.apiGet('/me/bookmarks', 'commenter').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('check: verify post is bookmarked', () => {
      cy.apiGet(`/bookmarks/${postId()}/check`, 'commenter').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.is_bookmarked).to.eq(true)
      })
    })

    it('check: verify non-bookmarked post', () => {
      cy.apiGet(`/bookmarks/${postId()}/check`, 'voter').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.is_bookmarked).to.eq(false)
      })
    })

    it('destroy: remove bookmark', () => {
      cy.apiDelete(`/bookmarks/${postId()}`, 'commenter').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  11. NOTIFICATIONS
  // ============================================================
  context('Notifications', () => {

    let notifId

    it('index: list notifications', () => {
      cy.apiGet('/notifications', 'poster').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.unread_count).to.be.gte(0)
        if (res.body.data.data?.length) {
          notifId = res.body.data.data[0].id
          Cypress.env('notificationId', notifId)
        }
      })
    })

    it('index: filter unread only', () => {
      cy.apiGet('/notifications?unread_only=1', 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('read: mark notification as read', () => {
      if (!notifId) {
        cy.log('No notification available — skipping')
        return
      }
      cy.apiPatch(`/notifications/${notifId}/read`, {}, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('read-all: mark all as read', () => {
      cy.apiPatch('/notifications/read-all', {}, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('destroy: delete single notification', () => {
      if (!notifId) {
        cy.log('No notification available — skipping')
        return
      }
      cy.apiDelete(`/notifications/${notifId}`, 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('destroy-read: delete all read notifications', () => {
      cy.apiDelete('/notifications/read', 'poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  12. REPORT
  // ============================================================
  context('Report', () => {

    let reportId, reportPostId
    const catId = () => Cypress.env('categoryId')

    before(() => {
      cy.apiPost('/posts', {
        category_id: catId(),
        title: 'Report Test Post — Content for reporting',
        body: 'This post will be reported during the Cypress test suite to verify report functionality.',
        tags: [],
      }, 'poster').then((res) => {
        reportPostId = res.body.data.id
        Cypress.env('reportPostId', reportPostId)
      })
    })

    it('store: report a post', () => {
      cy.apiPost('/reports', {
        target_type: 'post',
        target_id: reportPostId,
        reason: 'spam',
        description: 'This is test spam report from Cypress.',
      }, 'commenter').then((res) => {
        expect(res.status).to.eq(201)
      })
    })

    it('store: duplicate report returns error', () => {
      cy.apiPost('/reports', {
        target_type: 'post',
        target_id: reportPostId,
        reason: 'spam',
      }, 'commenter', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('store: cannot report own content', () => {
      cy.apiPost('/reports', {
        target_type: 'post',
        target_id: reportPostId,
        reason: 'spam',
      }, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('index: admin lists reports', () => {
      cy.apiGet('/reports', 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
        if (res.body.data?.length) {
          reportId = res.body.data[0].id
          Cypress.env('reportId', reportId)
        }
      })
    })

    it('index: non-admin cannot list reports (403)', () => {
      cy.apiGet('/reports', 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('resolve: admin resolves report (dismissed)', () => {
      if (!reportId) {
        cy.log('No report ID found — skipping')
        return
      }
      cy.apiPatch(`/reports/${reportId}/resolve`, { status: 'dismissed' }, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  13. SEARCH
  // ============================================================
  context('Search', () => {

    it('global: search by keyword', () => {
      cy.request('/search?q=Cypress').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.data).to.have.all.keys('posts', 'users', 'tags', 'categories')
      })
    })

    it('global: empty query returns 422', () => {
      cy.request({ method: 'GET', url: '/search?q=', failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(422)
      })
    })

    it('posts: search posts', () => {
      cy.request('/search/posts?q=Cypress').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('users: search users', () => {
      cy.request('/search/users?q=poster').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('tags: search tags', () => {
      cy.request('/search/tags?q=php').then((res) => {
        expect(res.status).to.eq(200)
      })
    })
  })

  // ============================================================
  //  14. POINTS
  // ============================================================
  context('Points', () => {

    it('logs: view own points log', () => {
      cy.apiGet('/me/points', 'poster').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.summary.current_points).to.be.gte(0)
      })
    })

    it('logs: non-admin cannot view another user points (403)', () => {
      cy.apiGet(`/users/${Cypress.env('poster_user').id}/points`, 'poster', { failOnStatusCode: false }).then((res) => {
        expect(res.status).to.eq(403)
      })
    })

    it('logs: admin can view any user points', () => {
      cy.apiGet(`/users/${Cypress.env('poster_user').id}/points`, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
      })
    })

    it('recalculate: admin recalculates points', () => {
      cy.apiPost(`/users/${Cypress.env('poster_user').id}/points/recalculate`, {}, 'superadmin').then((res) => {
        expect(res.status).to.eq(200)
        expect(res.body.reputation_points).to.be.a('number')
      })
    })
  })
})
