import DOMPurify from 'dompurify';

export const sanitizeHTML = (html) => {
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['p', 'a', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'h6'],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'class']
    });
}; 