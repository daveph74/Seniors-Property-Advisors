import SectionHead from './SectionHead';

export default function TeamIntroSection({ data, anchor }) {
    const members = data.members || [];
    const photo = {
        width: data.photoWidth ? `${data.photoWidth}px` : undefined,
        height: data.photoHeight ? `${data.photoHeight}px` : undefined,
    };

    return (
        <section className="team-intro" id={anchor}>
            <div className="container">
                <SectionHead {...data} />

                {members.length > 0 ? (
                    <div className="team-intro__grid">
                        {members.map((m, i) => (
                            <article className="team-member" key={i}>
                                {m.photo ? (
                                    <img
                                        className="team-member__photo"
                                        src={m.photo}
                                        alt={m.photoAlt || m.name || ''}
                                        style={photo}
                                        loading="lazy"
                                        decoding="async"
                                    />
                                ) : (
                                    <div className="team-member__photo team-member__photo--empty" style={photo} />
                                )}
                                <h3 className="team-member__name">{m.name}</h3>
                                {m.role ? <p className="team-member__role">{m.role}</p> : null}
                                {m.bio ? <p className="team-member__bio">{m.bio}</p> : null}
                            </article>
                        ))}
                    </div>
                ) : null}
            </div>
        </section>
    );
}
