function escapeHtml(str) {
    return str.replace(/[&<>'"]/g,
        tag => ({ '&': '&', '<': '<', '>': '>', "'": '&apos;', '"': '"' }[tag] || tag)
    );
}