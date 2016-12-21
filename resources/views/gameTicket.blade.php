@extends('layouts.layout')
@section('header')
    <link rel = "stylesheet" href = "/css/ticketsStyle.css" >

@stop
@section('content')

    <ul class="flex-container">
      @foreach($awayTeams as $awayTeam)
          @foreach($homeTeams as $homeTeam)


                  @if($homeTeam->game_id == $awayTeam->game_id)
                    <li class="flex-item1">
                        <h3> <a class="games" href="/tickets/{{$homeTeam->game_id}}">
                                @if(!empty($homeTeams_urls[$homeTeam->game_id][0]))
                                    <img class="games" src="{{$homeTeams_urls[$homeTeam->game_id][0]}}"/>

                                @endif

                                            {{$homeTeam->club_name}} VS {{$awayTeam->club_name}}

                                     @if(!empty($awayTeams_urls[$awayTeam->game_id][0]))
                                        <img class="games" src="{{$awayTeams_urls[$awayTeam->game_id][0]}}"/>

                                     @endif
                            </a></h3>
                          <br>
                    </li>
                @endif

        @endforeach
    @endforeach
    </ul>
@stop
@section('footer')
@stop
