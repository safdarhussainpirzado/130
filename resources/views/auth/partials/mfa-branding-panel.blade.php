@props([
    'eyebrow' => 'PAKISTAN’S MOTORWAY HELPLINE',
    'title' => 'Your call.<br><span>Our commitment.</span>',
    'subtitle' => 'A reassuring voice. A coordinated response.<br>A helping hand, when it matters most.',
])

<section class="service-story" aria-labelledby="story-heading">
    <div class="story-copy">
        <div class="story-eyebrow"><span aria-hidden="true"></span>{{ $eyebrow }}</div>
        <h1 id="story-heading">{!! $title !!}</h1>
        <p class="story-intro">{!! $subtitle !!}</p>
    </div>
    @include('auth.partials.helpline-flow')
    <div class="motorway-story" id="motorway-story" data-paused="false">
        @include('auth.partials.response-scene')
        <span class="scene-caption"><span aria-hidden="true">✦</span> On the road. By your side.</span>
    </div>
</section>
