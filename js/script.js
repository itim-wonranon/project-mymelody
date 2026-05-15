document.addEventListener('DOMContentLoaded', function() {
    // Password toggle logic
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    }
});

function getReactionIcon(type) {
    switch (type) {
        case 'heart': return '❤️';
        case 'haha': return '😆';
        case 'wow': return '😮';
        case 'sad': return '😢';
        case 'angry': return '😡';
        default: return '👍';
    }
}

function getReactionText(type) {
    switch (type) {
        case 'heart': return 'รักเลย';
        case 'haha': return 'ฮ่าๆ';
        case 'wow': return 'ว้าว';
        case 'sad': return 'เศร้า';
        case 'angry': return 'โกรธ';
        case 'like': return 'ถูกใจ';
        default: return 'ถูกใจ';
    }
}

function reactPost(postId, type) {
    const btn = document.querySelector("#reaction-btn-" + postId);
    const current = btn ? btn.getAttribute("data-current") : null;
    
    let finalType = type;
    if (type === 'like' && current && current !== 'none') {
        finalType = current;
    }

    const formData = new FormData();
    formData.append("post_id", postId);
    formData.append("type", finalType);

    fetch("ajax_community.php?action=react", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateReactionUI(postId, data.summary, 'post');
        } else {
            alert("Error: " + (data.error || "Unknown error"));
        }
    });
}

function reactComment(commentId, type) {
    const btn = document.querySelector("#comment-react-btn-" + commentId);
    const current = btn ? btn.getAttribute("data-current") : null;

    let finalType = type;
    if (type === 'like' && current && current !== 'none') {
        finalType = current;
    }

    const formData = new FormData();
    formData.append("comment_id", commentId);
    formData.append("type", finalType);

    fetch("ajax_community.php?action=react_comment", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateReactionUI(commentId, data.summary, 'comment');
        } else {
            alert("Error: " + (data.error || "Unknown error"));
        }
    });
}

function updateReactionUI(id, summary, targetType) {
    const prefix = targetType === 'post' ? '.reactions-count-' : '#comment-react-stats-';
    const containers = document.querySelectorAll(prefix + id);
    
    const btnId = targetType === 'post' ? "#reaction-btn-" + id : "#comment-react-btn-" + id;
    const btn = document.querySelector(btnId);
    if (btn) {
        if (summary.own) {
            btn.innerHTML = '<span class="text-primary fw-bold">' + getReactionText(summary.own) + '</span>';
            btn.setAttribute("data-current", summary.own);
        } else {
            btn.innerHTML = targetType === 'post' ? '<i class="far fa-thumbs-up me-2"></i> ถูกใจ' : 'ถูกใจ';
            btn.setAttribute("data-current", "none");
        }
    }

    containers.forEach(container => {
        if (summary.total > 0) {
            const iconClass = targetType === 'post' ? 'reaction-icons-group' : 'reaction-icons-small';
            let iconsHtml = `<div class="${iconClass}">`;
            summary.top.forEach(t => {
                iconsHtml += '<span>' + getReactionIcon(t.reaction_type) + '</span>';
            });
            iconsHtml += '</div>';
            container.innerHTML = iconsHtml + `<span class="ms-1" style="font-size: ${targetType === 'post' ? '0.85rem' : '0.7rem'};">${summary.total} คน</span>`;
            container.classList.remove('d-none');
        } else {
            container.innerHTML = '';
            container.classList.add('d-none');
        }
    });
}

function repostPost(postId) {
    const formData = new FormData();
    formData.append("post_id", postId);

    fetch("ajax_community.php?action=repost", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById("repost-btn-" + postId);
            const countEl = document.querySelector(".repost-count-" + postId);
            
            if (countEl) countEl.innerText = data.count + " รีโพสต์";
            
            if (data.status === "added") {
                if (btn) btn.classList.add("text-success", "fw-bold");
                Swal.fire({
                    icon: "success",
                    title: "รีโพสต์สำเร็จ!",
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000
                });
            } else {
                if (btn) btn.classList.remove("text-success", "fw-bold");
            }
        } else {
            alert(data.error || "กรุณาเข้าสู่ระบบก่อนรีโพสต์");
        }
    });
}

function toggleComments(postId) {
    const el = document.getElementById("comments-" + postId);
    if (!el) return;
    if (el.classList.contains("d-none")) {
        el.classList.remove("d-none");
        loadComments(postId);
    } else {
        el.classList.add("d-none");
    }
}

function loadComments(postId) {
    const list = document.getElementById("comments-list-" + postId);
    if (!list) return;
    
    fetch("ajax_community.php?action=get_comments&post_id=" + postId)
    .then(res => res.text())
    .then(html => {
        list.innerHTML = html;
    });
}

function submitComment(postId) {
    const input = document.getElementById("comment-input-" + postId);
    if (!input) return;
    const content = input.value.trim();
    if (!content) return;

    const formData = new FormData();
    formData.append("post_id", postId);
    formData.append("content", content);

    fetch("ajax_community.php?action=comment", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = "";
            loadComments(postId);
            const countLabels = document.querySelectorAll(".comment-count-label-" + postId);
            countLabels.forEach(el => {
                el.innerText = data.count + " ความเห็น";
            });
        } else {
            alert("Error: " + (data.error || "Unknown error"));
        }
    });
}

function showReplyInput(commentId) {
    const el = document.getElementById("reply-input-" + commentId);
    if (el) {
        el.classList.toggle('d-none');
        if (!el.classList.contains('d-none')) {
            const field = document.getElementById("reply-field-" + commentId);
            if (field) field.focus();
        }
    }
}

function submitReply(commentId, postId) {
    const input = document.getElementById("reply-field-" + commentId);
    const content = input.value.trim();
    if (!content) return;

    const formData = new FormData();
    formData.append("post_id", postId);
    formData.append("parent_id", commentId);
    formData.append("content", content);

    fetch("ajax_community.php?action=comment", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = "";
            const box = document.getElementById("reply-input-" + commentId);
            if (box) box.classList.add('d-none');
            loadComments(postId);
            const countLabels = document.querySelectorAll(".comment-count-label-" + postId);
            countLabels.forEach(el => {
                el.innerText = data.count + " ความเห็น";
            });
        }
    });
}

function editComment(commentId) {
    const content = document.getElementById("comment-content-" + commentId);
    const editBox = document.getElementById("edit-box-" + commentId);
    if (content && editBox) {
        content.classList.add("d-none");
        editBox.classList.remove("d-none");
    }
}

function cancelEdit(commentId) {
    const content = document.getElementById("comment-content-" + commentId);
    const editBox = document.getElementById("edit-box-" + commentId);
    if (content && editBox) {
        content.classList.remove("d-none");
        editBox.classList.add("d-none");
    }
}

function saveEdit(commentId, postId) {
    const field = document.getElementById("edit-field-" + commentId);
    const content = field ? field.value.trim() : "";
    if (!content) return;

    const formData = new FormData();
    formData.append("comment_id", commentId);
    formData.append("content", content);

    fetch("ajax_community.php?action=edit_comment", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadComments(postId);
        }
    });
}

function deleteComment(commentId) {
    if (typeof Swal === "undefined") {
        if (confirm("คุณต้องการลบความเห็นนี้ใช่หรือไม่?")) {
            performDelete(commentId);
        }
        return;
    }

    Swal.fire({
        title: "ลบความคิดเห็น?",
        text: "คุณต้องการลบความเห็นนี้ใช่หรือไม่?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "ลบ",
        cancelButtonText: "ยกเลิก",
        background: "#1a1a1a",
        color: "#fff"
    }).then((result) => {
        if (result.isConfirmed) {
            performDelete(commentId);
        }
    });
}

function performDelete(commentId) {
    const formData = new FormData();
    formData.append("comment_id", commentId);

    fetch("ajax_community.php?action=delete_comment", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadComments(data.post_id);
            const countLabels = document.querySelectorAll(".comment-count-label-" + data.post_id);
            countLabels.forEach(el => {
                el.innerText = data.count + " ความเห็น";
            });
        } else {
            alert(data.error || "เกิดข้อผิดพลาดในการลบ");
        }
    });
}

function editPost(postId) {
    const content = document.getElementById("post-content-" + postId);
    const editBox = document.getElementById("post-edit-box-" + postId);
    if (content && editBox) {
        content.classList.add("d-none");
        editBox.classList.remove("d-none");
    }
}

function cancelPostEdit(postId) {
    const content = document.getElementById("post-content-" + postId);
    const editBox = document.getElementById("post-edit-box-" + postId);
    if (content && editBox) {
        content.classList.remove("d-none");
        editBox.classList.add("d-none");
    }
}

function savePostEdit(postId) {
    const field = document.getElementById("post-edit-field-" + postId);
    const content = field ? field.value.trim() : "";
    if (!content) return;

    const formData = new FormData();
    formData.append("post_id", postId);
    formData.append("content", content);

    fetch("ajax_community.php?action=edit_post", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deletePost(postId) {
    if (typeof Swal === "undefined") {
        if (confirm("คุณต้องการลบโพสต์นี้ใช่หรือไม่?")) {
            performDeletePost(postId);
        }
        return;
    }

    Swal.fire({
        title: "ลบโพสต์?",
        text: "คุณต้องการลบโพสต์นี้ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "ลบ",
        cancelButtonText: "ยกเลิก",
        background: "#1a1a1a",
        color: "#fff"
    }).then((result) => {
        if (result.isConfirmed) {
            performDeletePost(postId);
        }
    });
}

function performDeletePost(postId) {
    const formData = new FormData();
    formData.append("post_id", postId);

    fetch("ajax_community.php?action=delete_post", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function submitEvent(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch("ajax_community.php?action=create_event", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'สร้างกิจกรรมสำเร็จ!',
                showConfirmButton: false,
                timer: 1500
            }).then(() => location.reload());
        } else {
            alert("Error: " + data.error);
        }
    });
}

function joinEvent(eventId, btn) {
    const formData = new FormData();
    formData.append("event_id", eventId);

    fetch("ajax_community.php?action=join_event", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check me-1"></i>เข้าร่วมแล้ว';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-success', 'disabled');
            Swal.fire({
                icon: 'success',
                title: 'เข้าร่วมกิจกรรมสำเร็จ!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            alert(data.error);
        }
    });
}

// Real-time Polling for Notifications & New Posts
let lastNotifId = 0;
let lastPostId = 0;

function initPolling(pId, nId) {
    lastPostId = pId;
    lastNotifId = nId;
    setInterval(pollUpdates, 10000); // Poll every 10 seconds
}

function pollUpdates() {
    fetch(`ajax_notifications.php?last_post_id=${lastPostId}&last_notif_id=${lastNotifId}`)
    .then(res => res.json())
    .then(data => {
        if (data.new_notifications && data.new_notifications.length > 0) {
            data.new_notifications.forEach(notif => {
                showNotifToast(notif);
                if (notif.id > lastNotifId) lastNotifId = notif.id;
            });
        }

        if (data.new_posts_count > 0) {
            showNewPostIndicator(data.new_posts_count);
        }

        // Update sidebar unread count badge
        const bellIcon = document.querySelector(".community-nav-link i.fa-bell");
        if (bellIcon) {
            const navLink = bellIcon.parentElement;
            let badge = navLink.querySelector(".badge");
            if (data.unread_count > 0) {
                if (badge) {
                    badge.innerText = data.unread_count;
                    badge.classList.remove("d-none");
                } else {
                    badge = document.createElement("span");
                    badge.className = "badge bg-danger rounded-pill ms-auto";
                    badge.innerText = data.unread_count;
                    navLink.appendChild(badge);
                }
            } else if (badge) {
                badge.classList.add("d-none");
            }
        }
    });
}

function showNotifToast(notif) {
    let container = document.querySelector(".notification-toast-container");
    if (!container) {
        container = document.createElement("div");
        container.className = "notification-toast-container";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = "notif-toast";
    
    let msg = "";
    if (notif.type === "reaction") msg = "แสดงความรู้สึกกับโพสต์ของคุณ";
    else if (notif.type === "comment") msg = "คอมเมนต์โพสต์ของคุณ";
    else if (notif.type === "repost") msg = "รีโพสต์เนื้อหาของคุณ";

    const senderName = notif.sender_name || notif.username;
    const avatar = notif.sender_avatar || "default_avatar.png";

    toast.innerHTML = `
        <img src="uploads/avatars/${avatar}" style="width: 35px; height: 35px; border-radius: 50%; margin-right: 12px; object-fit: cover;">
        <div style="font-size: 0.85rem;">
            <strong style="color: var(--primary-color);">${senderName}</strong> ${msg}
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add("fade-out");
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}

function showNewPostIndicator(count) {
    let indicator = document.getElementById("newPostIndicator");
    if (!indicator) {
        const feed = document.querySelector(".posts-feed");
        if (!feed) return;
        indicator = document.createElement("div");
        indicator.id = "newPostIndicator";
        indicator.className = "new-post-indicator";
        indicator.onclick = () => location.reload();
        feed.prepend(indicator);
    }
    indicator.innerHTML = `<i class="fas fa-arrow-up me-2"></i>มี ${count} โพสต์ใหม่ อัปเดตเลย`;
    indicator.style.display = "block";
}

function votePoll(postId, optionIndex) {
    const formData = new FormData();
    formData.append("post_id", postId);
    formData.append("option_index", optionIndex);

    fetch("ajax_community.php?action=vote_poll", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updatePollUI(postId, data);
        } else {
            alert(data.error || "เกิดข้อผิดพลาดในการโหวต");
        }
    });
}

function updatePollUI(postId, data) {
    const pollCard = document.querySelector(`.poll-card-container-${postId}`);
    if (!pollCard) return;

    const buttons = pollCard.querySelectorAll(".poll-btn");
    buttons.forEach((btn, idx) => {
        const count = data.votes[idx] || 0;
        const percent = data.total > 0 ? Math.round((count / data.total) * 100) : 0;
        
        // Change button to result view
        btn.classList.add("voted");
        if (idx === data.user_choice) btn.classList.add("active-choice");
        else btn.classList.remove("active-choice");

        btn.innerHTML = `
            <div class="d-flex justify-content-between align-items-center w-100 position-relative" style="z-index: 2;">
                <span>${btn.getAttribute("data-label")}</span>
                <span>${percent}%</span>
            </div>
            <div class="poll-progress" style="width: ${percent}%;"></div>
        `;
    });

    const totalEl = pollCard.querySelector(".poll-total-count");
    if (totalEl) totalEl.innerText = data.total + " โหวต";
}

function showReactions(id, type) {
    const modalBody = document.getElementById("reactionsModalBody");
    if (!modalBody) return;

    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    const modal = new bootstrap.Modal(document.getElementById('reactionsModal'));
    modal.show();

    fetch(`ajax_community.php?action=get_reactions_list&id=${id}&type=${type}`)
    .then(res => res.text())
    .then(html => {
        modalBody.innerHTML = html;
    });
}
