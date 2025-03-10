import { sanitizeHTML } from '../utils/sanitize';

const UpdateItem = ({ update }) => (
    <article className="live-update-item">
        <header>
            <h3 dangerouslySetInnerHTML={{ __html: sanitizeHTML(update.title.rendered) }} />
            <time dateTime={update.date}>
                {new Date(update.date).toLocaleString()}
            </time>
        </header>
        <div 
            dangerouslySetInnerHTML={{ 
                __html: sanitizeHTML(update.content.rendered) 
            }} 
        />
    </article>
);

export default UpdateItem; 