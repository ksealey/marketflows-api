@extends('templates.site')

@section('title', 'Pricing')

@section('content')
    <section class="page-header">
        <h1>Pricing</h1>
        <p>Marketflows is a pay-as-you go service. You will only be charged for your monthly utilization without minimums or long-term contracts.</p>
    </section>
    <section class="page-content">
        <div class="table">
            <img src=""/>
            <h2>Call Tracking</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Cost</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Local Call</td>
                        <td>$0.04</td>
                        <td>1 minute</td>
                    </tr>
                    <tr>
                        <td>Toll Free Call</td>
                        <td>$0.07</td>
                        <td>1 minute</td>
                    </tr>
                    <tr>
                        <td>Local Phone Number</td>
                        <td>$3.00</td>
                        <td>1/month</td>
                    </tr>
                    <tr>
                        <td>Toll Free Phone Number</td>
                        <td>$5.00</td>
                        <td>1/month</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table">
            <h2>SMS Tracking</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Cost</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Incoming SMS</td>
                        <td>$0.015</td>
                        <td>1 SMS</td>
                    </tr>
                    <tr>
                        <td>Forwarded SMS</td>
                        <td>$0.02</td>
                        <td>1 SMS</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </section>
@endsection